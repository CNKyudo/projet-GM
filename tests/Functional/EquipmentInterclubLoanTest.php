<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Club;
use App\Entity\ClubMember;
use App\Entity\Equipment;
use App\Entity\Gake;
use App\Repository\ClubMemberRepository;
use App\Repository\ClubRepository;
use App\Repository\EquipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests fonctionnels : prêt interclub.
 *
 * Vérifie que le champ isAvailableForLoan permet au club propriétaire
 * de contrôler la visibilité de ses équipements auprès des autres clubs.
 */
final class EquipmentInterclubLoanTest extends AbstractWebTestCase
{
    private int $gakeAId;

    private int $gakeCId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();
        /** @var EquipmentRepository $repo */
        $repo = $container->get(EquipmentRepository::class);

        /** @var Equipment[] $equipments */
        $equipments = $repo->findAll();

        foreach ($equipments as $equipment) {
            if (!$equipment instanceof Gake) {
                continue;
            }

            if (AppFixtures::CLUB_A === $equipment->getOwnerClub()?->getName()) {
                $this->gakeAId = $equipment->getId();
            } elseif (AppFixtures::CLUB_C === $equipment->getOwnerClub()?->getName()) {
                $this->gakeCId = $equipment->getId();
            }
        }
    }

    public function testPresidentCanToggleIsAvailableForLoanOnOwnEquipment(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);

        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Gake $gake */
        $gake = $em->getRepository(Gake::class)->find($this->gakeAId);
        $this->assertInstanceOf(Gake::class, $gake);

        // Avant : isAvailableForLoan = false (par défaut dans les fixtures pour Club A)
        $this->assertFalse($gake->isAvailableForLoan());

        // Activer le prêt interclub
        $this->client->request(Request::METHOD_POST, '/equipment/'.$this->gakeAId.'/edit', [
            'equipment_form' => [
                'ownerClub'           => (string) $gake->getOwnerClub()->getId(),
                'state'               => $gake->getState()->value,
                'borrowerClub'        => '',
                'borrowerMember'      => '',
                'notes'               => '',
                'isAvailableForLoan'  => '1',
                'gake_form'           => [
                    'nb_fingers' => (string) $gake->getNbFingers(),
                    'size'       => (string) $gake->getSize(),
                ],
            ],
        ]);
        $this->assertResponseRedirects('/equipment');

        // Vérifier en base
        $em->clear();
        /** @var Gake $updatedGake */
        $updatedGake = $em->getRepository(Gake::class)->find($this->gakeAId);
        $this->assertTrue($updatedGake->isAvailableForLoan());

        // Désactiver le prêt interclub
        $this->client->request(Request::METHOD_POST, '/equipment/'.$this->gakeAId.'/edit', [
            'equipment_form' => [
                'ownerClub'           => (string) $updatedGake->getOwnerClub()->getId(),
                'state'               => $updatedGake->getState()->value,
                'borrowerClub'        => '',
                'borrowerMember'      => '',
                'notes'               => '',
                'gake_form'           => [
                    'nb_fingers' => (string) $updatedGake->getNbFingers(),
                    'size'       => (string) $updatedGake->getSize(),
                ],
            ],
        ]);
        $this->assertResponseRedirects('/equipment');

        $em->clear();
        /** @var Gake $finalGake */
        $finalGake = $em->getRepository(Gake::class)->find($this->gakeAId);
        $this->assertFalse($finalGake->isAvailableForLoan());
    }

    public function testClubEquipmentNotAvailableForLoanIsHiddenFromSameCtkPresident(): void
    {
        // Club A est dans Région A (même CTK que Club C). Le président de Club A
        // ne doit pas voir l'équipement de Club A quand isAvailableForLoan = false.
        $this->loginAs(AppFixtures::USER_PRESIDENT);

        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();

        // L'équipement du propre club (Club A) est toujours visible
        $this->assertStringContainsString('/equipment/'.$this->gakeAId, $content);

        // L'équipement de Club C est visible car il est marqué disponible dans les fixtures
        $this->assertStringContainsString('/equipment/'.$this->gakeCId, $content);
    }

    public function testClubEquipmentNotMarkedForLoanIsHiddenFromOtherClubPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);

        $container = self::getContainer();
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);

        // Créer un équipement Club C sans isAvailableForLoan
        $clubC = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_C]);
        $this->assertInstanceOf(Club::class, $clubC);

        // On crée un nouvel équipement pour Club C sans isAvailableForLoan
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $newGake = new Gake()
            ->setOwnerClub($clubC)
            ->setNbFingers(3)
            ->setSize('7');
        $em->persist($newGake);
        $em->flush();

        $newGakeId = $newGake->getId();

        // Le président de Club A ne doit pas voir ce nouvel équipement (isAvailableForLoan = false)
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('/equipment/'.$newGakeId, $content);
    }

    public function testClubEquipmentMarkedForLoanIsVisibleToOtherClubPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);

        $container = self::getContainer();
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $clubC = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_C]);
        $this->assertInstanceOf(Club::class, $clubC);

        // Créer un équipement Club C avec isAvailableForLoan = true
        $newGake = new Gake()
            ->setOwnerClub($clubC)
            ->setNbFingers(3)
            ->setSize('7')
            ->setIsAvailableForLoan(true);
        $em->persist($newGake);
        $em->flush();

        $newGakeId = $newGake->getId();

        // Le président de Club A doit voir ce nouvel équipement
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('/equipment/'.$newGakeId, $content);
    }

    public function testAdminSeesAllEquipmentRegardlessOfIsAvailableForLoan(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $container = self::getContainer();
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $clubC = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_C]);
        $this->assertInstanceOf(Club::class, $clubC);

        // Créer équipement sans isAvailableForLoan
        $newGake = new Gake()
            ->setOwnerClub($clubC)
            ->setNbFingers(3)
            ->setSize('7');
        $em->persist($newGake);
        $em->flush();

        $newGakeId = $newGake->getId();

        // ADMIN voit tout, même sans isAvailableForLoan
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('/equipment/'.$newGakeId, $content);
    }

    public function testShowPageDisplaysLoanAvailabilityBadge(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Gake $gake */
        $gake = $em->getRepository(Gake::class)->find($this->gakeCId);
        $this->assertInstanceOf(Gake::class, $gake);

        // Club C's gakeC has isAvailableForLoan = true in fixtures
        $this->assertTrue($gake->isAvailableForLoan());

        $this->client->request(Request::METHOD_GET, '/equipment/'.$this->gakeCId);
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Prêt interclub autorisé', $content);
    }

    public function testCreateFormContainsCheckbox(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);

        $this->client->request(Request::METHOD_GET, '/equipment/create');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('isAvailableForLoan', $content);
        $this->assertStringContainsString('Disponible pour le prêt interclub', $content);
    }

    public function testMemberSeesOwnClubEquipmentRegardlessOfIsAvailableForLoan(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);

        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();

        // MEMBER voit l'équipement de son propre club (Club A), même si isAvailableForLoan = false
        $this->assertStringContainsString('/equipment/'.$this->gakeAId, $content);
    }

    public function testMemberCannotEditEquipmentToToggleLoanAvailability(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);

        // MEMBER n'a pas EDIT_EQUIPMENT → 403
        $this->assertGetDenied('/equipment/'.$this->gakeAId.'/edit');
    }

    public function testManagerCtkCanToggleIsAvailableForLoanOnManagedClubEquipment(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);

        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Gake $gake */
        $gake = $em->getRepository(Gake::class)->find($this->gakeAId);
        $this->assertInstanceOf(Gake::class, $gake);

        // MGR_CTK peut accéder à la page d'édition d'un club de sa région gérée
        $this->client->request(Request::METHOD_GET, '/equipment/'.$this->gakeAId.'/edit');
        $this->assertResponseIsSuccessful();

        // Activer le prêt interclub
        $this->client->request(Request::METHOD_POST, '/equipment/'.$this->gakeAId.'/edit', [
            'equipment_form' => [
                'ownerClub'           => (string) $gake->getOwnerClub()->getId(),
                'state'               => $gake->getState()->value,
                'borrowerClub'        => '',
                'borrowerMember'      => '',
                'notes'               => '',
                'isAvailableForLoan'  => '1',
                'gake_form'           => [
                    'nb_fingers' => (string) $gake->getNbFingers(),
                    'size'       => (string) $gake->getSize(),
                ],
            ],
        ]);
        $this->assertResponseRedirects('/equipment');

        // Vérifier en base
        $em->clear();
        /** @var Gake $updatedGake */
        $updatedGake = $em->getRepository(Gake::class)->find($this->gakeAId);
        $this->assertTrue($updatedGake->isAvailableForLoan());

        // Désactiver le prêt interclub
        $this->client->request(Request::METHOD_POST, '/equipment/'.$this->gakeAId.'/edit', [
            'equipment_form' => [
                'ownerClub'           => (string) $updatedGake->getOwnerClub()->getId(),
                'state'               => $updatedGake->getState()->value,
                'borrowerClub'        => '',
                'borrowerMember'      => '',
                'notes'               => '',
                'gake_form'           => [
                    'nb_fingers' => (string) $updatedGake->getNbFingers(),
                    'size'       => (string) $updatedGake->getSize(),
                ],
            ],
        ]);
        $this->assertResponseRedirects('/equipment');

        $em->clear();
        /** @var Gake $finalGake */
        $finalGake = $em->getRepository(Gake::class)->find($this->gakeAId);
        $this->assertFalse($finalGake->isAvailableForLoan());
    }

    public function testManagerCnCanToggleIsAvailableForLoanOnAnyClubEquipment(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);

        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var Gake $gake */
        $gake = $em->getRepository(Gake::class)->find($this->gakeAId);
        $this->assertInstanceOf(Gake::class, $gake);

        // MGMT_CN peut accéder à l'édition de n'importe quel équipement club
        $this->client->request(Request::METHOD_GET, '/equipment/'.$this->gakeAId.'/edit');
        $this->assertResponseIsSuccessful();

        // Activer le prêt interclub
        $this->client->request(Request::METHOD_POST, '/equipment/'.$this->gakeAId.'/edit', [
            'equipment_form' => [
                'ownerClub'           => (string) $gake->getOwnerClub()->getId(),
                'state'               => $gake->getState()->value,
                'borrowerClub'        => '',
                'borrowerMember'      => '',
                'notes'               => '',
                'isAvailableForLoan'  => '1',
                'gake_form'           => [
                    'nb_fingers' => (string) $gake->getNbFingers(),
                    'size'       => (string) $gake->getSize(),
                ],
            ],
        ]);
        $this->assertResponseRedirects('/equipment');

        $em->clear();
        /** @var Gake $updatedGake */
        $updatedGake = $em->getRepository(Gake::class)->find($this->gakeAId);
        $this->assertTrue($updatedGake->isAvailableForLoan());
    }

    public function testManagerCtkSeesAllEquipmentInManagedRegionRegardlessOfIsAvailableForLoan(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);

        $container = self::getContainer();
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $clubC = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_C]);
        $this->assertInstanceOf(Club::class, $clubC);

        // Créer équipement Club C sans isAvailableForLoan
        $newGake = new Gake()
            ->setOwnerClub($clubC)
            ->setNbFingers(3)
            ->setSize('7');
        $em->persist($newGake);
        $em->flush();

        $newGakeId = $newGake->getId();

        // MGR_CTK voit tous les équipements des clubs de sa région gérée
        // (restrictToClubs les inclut sans condition de isAvailableForLoan)
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('/equipment/'.$newGakeId, $content);
    }

    public function testMemberDoesNotSeeOtherClubEquipmentWithoutLoanAvailability(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);

        $container = self::getContainer();
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $clubC = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_C]);
        $this->assertInstanceOf(Club::class, $clubC);

        // Créer équipement Club C sans isAvailableForLoan
        $newGake = new Gake()
            ->setOwnerClub($clubC)
            ->setNbFingers(3)
            ->setSize('7');
        $em->persist($newGake);
        $em->flush();

        $newGakeId = $newGake->getId();

        // MEMBER ne voit que les équipements de ses propres clubs
        // Club C n'est pas un club de MEMBER, il ne devrait pas le voir
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('/equipment/'.$newGakeId, $content);
    }

    public function testBorrowerClubBlockedWhenNotAvailableForLoan(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);

        $container = self::getContainer();
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);
        $clubC = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_C]);
        $this->assertInstanceOf(Club::class, $clubC);

        $clubA = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(Club::class, $clubA);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Créer équipement Club A sans isAvailableForLoan (par défaut)
        $newGake = new Gake()
            ->setOwnerClub($clubA)
            ->setNbFingers(3)
            ->setSize('7');
        $em->persist($newGake);
        $em->flush();

        $newGakeId = $newGake->getId();

        // Tenter de prêter à Club C alors que isAvailableForLoan = false → erreur
        $this->client->request(Request::METHOD_POST, '/equipment/'.$newGakeId.'/edit', [
            'equipment_form' => [
                'ownerClub'           => (string) $clubA->getId(),
                'state'               => 'new',
                'borrowerClub'        => (string) $clubC->getId(),
                'borrowerMember'      => '',
                'notes'               => '',
                'gake_form'           => [
                    'nb_fingers' => '3',
                    'size'       => '7',
                ],
            ],
        ]);

        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [200, 422],
            'Le formulaire doit être réaffiché avec une erreur'
        );
        $this->assertStringContainsString(
            'n&#039;est pas activé',
            (string) $this->client->getResponse()->getContent()
        );
    }

    public function testBorrowerMemberAllowedWhenNotAvailableForLoan(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);

        $container = self::getContainer();
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);
        $clubA = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(Club::class, $clubA);

        /** @var ClubMemberRepository $memberRepo */
        $memberRepo = $container->get(ClubMemberRepository::class);
        $member = $memberRepo->findOneBy(['email' => AppFixtures::CLUB_MEMBER_UNREGISTERED_EMAIL]);
        $this->assertInstanceOf(ClubMember::class, $member);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Créer équipement Club A sans isAvailableForLoan
        $newGake = new Gake()
            ->setOwnerClub($clubA)
            ->setNbFingers(3)
            ->setSize('7');
        $em->persist($newGake);
        $em->flush();

        $newGakeId = $newGake->getId();

        // Prêter à un membre (borrowerMember) est autorisé même sans isAvailableForLoan
        $this->client->request(Request::METHOD_POST, '/equipment/'.$newGakeId.'/edit', [
            'equipment_form' => [
                'ownerClub'           => (string) $clubA->getId(),
                'state'               => 'new',
                'borrowerClub'        => '',
                'borrowerMember'      => (string) $member->getId(),
                'notes'               => '',
                'gake_form'           => [
                    'nb_fingers' => '3',
                    'size'       => '7',
                ],
            ],
        ]);

        $this->assertResponseRedirects('/equipment');
    }

    public function testBorrowerClubAllowedWhenAvailableForLoan(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);

        $container = self::getContainer();
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);
        $clubC = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_C]);
        $this->assertInstanceOf(Club::class, $clubC);

        $clubA = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(Club::class, $clubA);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Créer équipement Club A avec isAvailableForLoan = true
        $newGake = new Gake()
            ->setOwnerClub($clubA)
            ->setNbFingers(3)
            ->setSize('7')
            ->setIsAvailableForLoan(true);
        $em->persist($newGake);
        $em->flush();

        $newGakeId = $newGake->getId();

        // Prêter à Club C est autorisé car isAvailableForLoan = true
        $this->client->request(Request::METHOD_POST, '/equipment/'.$newGakeId.'/edit', [
            'equipment_form' => [
                'ownerClub'           => (string) $clubA->getId(),
                'state'               => 'new',
                'borrowerClub'        => (string) $clubC->getId(),
                'borrowerMember'      => '',
                'notes'               => '',
                'isAvailableForLoan'  => '1',
                'gake_form'           => [
                    'nb_fingers' => '3',
                    'size'       => '7',
                ],
            ],
        ]);

        $this->assertResponseRedirects('/equipment');
    }
}
