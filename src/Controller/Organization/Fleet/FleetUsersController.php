<?php

namespace App\Controller\Organization\Fleet;

use App\Entity\User;
use App\Repository\CitizenRepository;
use App\Service\FleetOrganizationGuard;
use App\Service\Organization\ShipFamilyFilterFactory;
use App\Service\ShipInfosProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;

class FleetUsersController extends AbstractController
{
    private $security;
    private $fleetOrganizationGuard;
    private $shipInfosProvider;
    private $logger;
    private $citizenRepository;
    private $shipFamilyFilterFactory;

    public function __construct(
        Security $security,
        FleetOrganizationGuard $fleetOrganizationGuard,
        ShipInfosProviderInterface $shipInfosProvider,
        LoggerInterface $logger,
        CitizenRepository $citizenRepository,
        ShipFamilyFilterFactory $shipFamilyFilterFactory
    ) {
        $this->security = $security;
        $this->fleetOrganizationGuard = $fleetOrganizationGuard;
        $this->shipInfosProvider = $shipInfosProvider;
        $this->logger = $logger;
        $this->citizenRepository = $citizenRepository;
        $this->shipFamilyFilterFactory = $shipFamilyFilterFactory;
    }

    public function __invoke(Request $request, string $organizationSid, string $providerShipName): Response
    {
        $page = $request->query->getInt('page', 1);
        $itemsPerPage = 10;

        if (null !== $response = $this->fleetOrganizationGuard->checkAccessibleOrganization($organizationSid)) {
            return $response;
        }

        // If viewer is not in this orga, he doesn't see the users
        if ($this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            /** @var User $user */
            $user = $this->security->getUser();
            $citizen = $user->getCitizen();
            if ($citizen === null) {
                return new JsonResponse([
                    'error' => 'no_citizen_created',
                    'errorMessage' => 'Your RSI account must be linked first. Go to the <a href="/profile">profile page</a>.',
                ], 400);
            }
            if (!$citizen->hasOrganization($organizationSid)) {
                return new JsonResponse([
                    'users' => [],
                    'page' => 1,
                    'lastPage' => 1,
                    'total' => 0,
                ]);
            }
        } else {
            return new JsonResponse([
                'users' => [],
                'page' => 1,
                'lastPage' => 1,
                'total' => 0,
            ]);
        }

        $shipFamilyFilter = $this->shipFamilyFilterFactory->create($request, $organizationSid);

        $shipName = $this->shipInfosProvider->transformProviderToHangar($providerShipName);
        $shipInfo = $this->shipInfosProvider->getShipByName($providerShipName);
        if ($shipInfo === null) {
            $this->logger->warning('Ship not found in the ship infos provider.', ['hangarShipName' => $providerShipName, 'provider' => get_class($this->shipInfosProvider)]);

            return $this->json([]);
        }

        // filtering
        if (count($shipFamilyFilter->shipSizes) > 0 && !in_array($shipInfo->size, $shipFamilyFilter->shipSizes, false)) {
            return $this->json([]);
        }
        if ($shipFamilyFilter->shipStatus !== null && $shipFamilyFilter->shipStatus !== $shipInfo->productionStatus) {
            return $this->json([]);
        }

        $loggedCitizen = null;
        if ($this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $loggedCitizen = $this->getUser()->getCitizen();
        }

        $countOwners = $this->citizenRepository->countOwnersOfShip($organizationSid, $shipName, $loggedCitizen, $shipFamilyFilter);
        $users = $this->citizenRepository->getOwnersOfShip(
            $organizationSid,
            $shipName,
            $loggedCitizen,
            $shipFamilyFilter,
            $page,
            $itemsPerPage
        );
        $lastPage = (int) ceil($countOwners / $itemsPerPage);

        return $this->json([
            'users' => $users,
            'page' => $page,
            'lastPage' => $lastPage > 0 ? $lastPage : 1,
            'total' => $countOwners,
        ], 200, [], ['groups' => 'orga_fleet']);
    }
}
