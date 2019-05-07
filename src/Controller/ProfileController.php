<?php

namespace App\Controller;

use App\Domain\CitizenInfos;
use App\Domain\HandleSC;
use App\Entity\Citizen;
use App\Entity\User;
use App\Exception\NotFoundHandleSCException;
use App\Form\Dto\LinkAccount;
use App\Form\Dto\UpdateHandle;
use App\Form\LinkAccountForm;
use App\Form\UpdateHandleForm;
use App\Repository\CitizenRepository;
use App\Repository\UserRepository;
use App\Service\CitizenInfosProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileController extends AbstractController
{
    private $logger;
    private $translator;
    private $citizenInfosProvider;
    private $citizenRepository;
    private $userRepository;
    private $security;
    private $formFactory;
    private $entityManager;

    public function __construct(
        LoggerInterface $logger,
        TranslatorInterface $translator,
        CitizenInfosProviderInterface $citizenInfosProvider,
        CitizenRepository $citizenRepository,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        Security $security,
        FormFactoryInterface $formFactory
    ) {
        $this->logger = $logger;
        $this->translator = $translator;
        $this->citizenInfosProvider = $citizenInfosProvider;
        $this->citizenRepository = $citizenRepository;
        $this->userRepository = $userRepository;
        $this->security = $security;
        $this->formFactory = $formFactory;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/profile", name="profile", methods={"GET"})
     *
     * Retrieves profile infos : user properties.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $this->json($user, 200, [], ['groups' => 'profile']);
    }

    /**
     * @Route("/save-preferences", name="save_preferences", methods={"POST"}, condition="request.getContentType() == 'json'")
     */
    public function savePreferences(Request $request): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $content = \json_decode($request->getContent(), true);

        // TODO : make a formtype
        $user->setPublicChoice($content['publicChoice'] ?? User::PUBLIC_CHOICE_PRIVATE);

        $this->entityManager->flush();

        return $this->json(null, 204);
    }

    /**
     * @Route("/update-handle", name="update_handle", methods={"POST"})
     *
     * Update the SC Handle of logged User. Compare the current and target Citizen numbers.
     */
    public function updateHandle(Request $request): Response
    {
        $updateHandle = new UpdateHandle();
        $form = $this->formFactory->createNamedBuilder('', UpdateHandleForm::class, $updateHandle)->getForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->json([
                'error' => 'not_submitted_form',
                'errorMessage' => 'No data has been submitted.',
            ], 400);
        }
        if (!$form->isValid()) {
            $formErrors = $form->getErrors(true);
            $errors = [];
            foreach ($formErrors as $formError) {
                $errors[] = $formError->getMessage();
            }

            return $this->json([
                'error' => 'invalid_form',
                'formErrors' => $errors,
            ], 400);
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $citizen = $user->getCitizen();

        try {
            $citizenInfos = $this->citizenInfosProvider->retrieveInfos(new HandleSC($updateHandle->handleSC));
            if (!$citizenInfos->numberSC->equals($citizen->getNumber())) {
                return $this->json([
                    'error' => 'invalid_form',
                    'formErrors' => ['This SC handle does not have the same SC number than yours.'],
                ], 400);
            }

            $citizen->setActualHandle(new HandleSC($updateHandle->handleSC));
            $this->citizenRepository->update($citizen);
        } catch (NotFoundHandleSCException $e) {
            return $this->json([
                'error' => 'not_found_handle',
                'errorMessage' => sprintf('The SC handle %s does not exist.', $updateHandle->handleSC),
            ], 400);
        }

        return $this->json(null, 204);
    }

    /**
     * @Route("/link-account", name="link_account", methods={"POST"})
     *
     * Link RSI Account (with SC Handle and Bio token) with the actual logged User.
     */
    public function linkAccount(Request $request): Response
    {
        $linkAccount = new LinkAccount();
        $form = $this->formFactory->createNamedBuilder('', LinkAccountForm::class, $linkAccount)->getForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->json([
                'error' => 'not_submitted_form',
                'errorMessage' => 'No data has been submitted.',
            ], 400);
        }
        if (!$form->isValid()) {
            $formErrors = $form->getErrors(true);
            $errors = [];
            foreach ($formErrors as $formError) {
                $errors[] = $formError->getMessage();
            }

            return $this->json([
                'error' => 'invalid_form',
                'formErrors' => $errors,
            ], 400);
        }

        /** @var User $user */
        $user = $this->security->getUser();

        try {
            $citizenInfos = $this->citizenInfosProvider->retrieveInfos(new HandleSC($linkAccount->handleSC));
            if (!$this->isTokenValid($user, $citizenInfos)) {
                return $this->json([
                    'error' => 'invalid_form',
                    'formErrors' => ['Your RSI bio does not contain this token.'],
                ], 400);
            }
            $this->attachCitizenToUser($user, $citizenInfos);
        } catch (NotFoundHandleSCException $e) {
            return $this->json([
                'error' => 'not_found_handle',
                'errorMessage' => sprintf('The SC handle %s does not exist.', $linkAccount->handleSC),
            ], 400);
        }

        return $this->json(null, 204);
    }

    private function attachCitizenToUser(User $user, CitizenInfos $citizenInfos): void
    {
        $citizen = $this->citizenRepository->findOneBy(['actualHandle' => $citizenInfos->handle]);
        $isNew = $citizen === null;
        if ($isNew) {
            $citizen = new Citizen(Uuid::uuid4());
        }

        $citizen
            ->setNumber(clone $citizenInfos->numberSC)
            ->setActualHandle(clone $citizenInfos->handle)
            ->setBio($citizenInfos->bio);
        foreach ($citizenInfos->organisations as $organisation) {
            $citizen->addOrganisation(clone $organisation);
        }

        if ($isNew) {
            $this->entityManager->persist($citizen);
        }
        $user->setCitizen($citizen);
        $this->entityManager->flush();
    }

    private function isTokenValid(User $user, CitizenInfos $citizenInfos): bool
    {
        return stripos($citizenInfos->bio, $user->getToken()) !== false;
    }
}
