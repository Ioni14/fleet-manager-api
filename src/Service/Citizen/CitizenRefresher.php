<?php

namespace App\Service\Citizen;

use Algatux\InfluxDbBundle\Events\AbstractInfluxDbEvent;
use Algatux\InfluxDbBundle\Events\DeferredHttpEvent;
use App\Domain\CitizenInfos;
use App\Domain\SpectrumIdentification;
use App\Entity\Citizen;
use App\Entity\CitizenOrganization;
use App\Entity\Organization;
use App\Event\CitizenRefreshedEvent;
use App\Repository\OrganizationRepository;
use App\Service\Organization\InfosProvider\OrganizationInfosProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use InfluxDB\Database;
use InfluxDB\Point;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CitizenRefresher
{
    private $entityManager;
    private $organizationRepository;
    private $organizationInfosProvider;
    private $eventDispatcher;
    private $requestStack;

    public function __construct(
        EntityManagerInterface $entityManager,
        OrganizationRepository $organizationRepository,
        OrganizationInfosProviderInterface $organizationInfosProvider,
        EventDispatcherInterface $eventDispatcher,
        RequestStack $requestStack
    ) {
        $this->entityManager = $entityManager;
        $this->organizationRepository = $organizationRepository;
        $this->organizationInfosProvider = $organizationInfosProvider;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
    }

    public function refreshCitizen(Citizen $citizen, CitizenInfos $citizenInfos): void
    {
        foreach ($citizenInfos->organizations as $orgaInfo) {
            $orga = $this->organizationRepository->findOneBy(['organizationSid' => $orgaInfo->sid->getSid()]);
            $providerOrgaInfos = $this->organizationInfosProvider->retrieveInfos(new SpectrumIdentification($orgaInfo->sid->getSid()));
            if ($orga === null) {
                $orga = new Organization(Uuid::uuid4());
                $orga->setOrganizationSid($orgaInfo->sid->getSid());
                $this->entityManager->persist($orga);

                $this->eventDispatcher->dispatch(new DeferredHttpEvent([new Point(
                    'app.new_organization',
                    1,
                    ['orga_id' => $orga->getId(), 'orga_name' => $providerOrgaInfos->fullname, 'host' => $this->requestStack->getCurrentRequest()->getHost()],
                )], Database::PRECISION_SECONDS), AbstractInfluxDbEvent::NAME);
            }
            $orga->setAvatarUrl($providerOrgaInfos->avatarUrl);
            $orga->setName($providerOrgaInfos->fullname);
        }
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new CitizenRefreshedEvent($citizen, $citizenInfos));

        $citizen->setNickname($citizenInfos->nickname);
        $citizen->setBio($citizenInfos->bio);
        $citizen->setAvatarUrl($citizenInfos->avatarUrl);
        $citizen->setLastRefresh(new \DateTimeImmutable());
        $citizen->setRedactedMainOrga($citizenInfos->redactedMainOrga);
        $citizen->setCountRedactedOrganizations($citizenInfos->countRedactedOrganizations);

        // remove left orga
        foreach ($citizen->getOrganizations() as $organization) {
            $sid = $organization->getOrganization()->getOrganizationSid();
            $foundOrgaInfo = null;
            foreach ($citizenInfos->organizations as $orgaInfo) {
                if ($orgaInfo->sid->getSid() === $sid) {
                    $foundOrgaInfo = $orgaInfo;
                    break;
                }
            }
            if ($foundOrgaInfo === null) {
                $citizen->removeOrganization($organization);
                $this->entityManager->remove($organization);
                continue;
            }
        }

        // remove duplicated CitizenOrganization
        $seenCitizenOrgas = [];
        foreach ($citizen->getOrganizations() as $organization) {
            if (isset($seenCitizenOrgas[$organization->getOrganization()->getId()->toString()])) {
                // duplication
                $this->entityManager->remove($organization);
                continue;
            }
            $seenCitizenOrgas[$organization->getOrganization()->getId()->toString()] = $organization;
        }

        // refresh & join new orga
        $citizen->setMainOrga(null);
        foreach ($citizenInfos->organizations as $orgaInfo) {
            $citizenOrga = null;
            foreach ($seenCitizenOrgas as $organization) {
                if ($orgaInfo->sid->getSid() === $organization->getOrganization()->getOrganizationSid()) {
                    $citizenOrga = $organization;
                    break;
                }
            }
            if ($citizenOrga === null) {
                $citizenOrga = new CitizenOrganization(Uuid::uuid4());
                $citizenOrga->setCitizen($citizen);
                $this->entityManager->persist($citizenOrga);
            }
            $orga = $this->organizationRepository->findOneBy(['organizationSid' => $orgaInfo->sid->getSid()]);
            $citizenOrga->setOrganization($orga);
            $citizenOrga->setOrganizationSid($orgaInfo->sid->getSid());
            $citizenOrga->setRank($orgaInfo->rank);
            $citizenOrga->setRankName($orgaInfo->rankName);

            if ($citizenInfos->mainOrga === $orgaInfo) {
                $citizen->setMainOrga($citizenOrga);
            }
        }
    }
}
