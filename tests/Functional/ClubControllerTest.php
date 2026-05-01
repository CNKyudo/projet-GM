<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Club;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\ClubRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels : ClubController.
 *
 * Routes testées :
 *   GET  /club/               → IS_AUTHENTICATED_FULLY
 *   GET  /club/new            → CREATE_CLUB
 *   GET  /club/{id}           → IS_AUTHENTICATED_FULLY
 *   GET  /club/{id}/edit      → EDIT_CLUB (avec sujet)
 *   POST /club/{id} (DELETE)  → DELETE_CLUB (avec sujet)
 *
 * Matrice des droits attendus (source : Détails droits Kyudo gestion matériel DHD.csv)
 * ┌────────────────────────────────┬───────┬────────┬──────────┬──────────────┬─────────────┬────────────┬───────┐
 * │ Action                         │ USER  │ MEMBER │ PRESIDENT│ MGMT_CLUB    │ MGMT_CTK    │ MGMT_CN    │ ADMIN │
 * ├────────────────────────────────┼───────┼────────┼──────────┼──────────────┼─────────────┼────────────┼───────┤
 * │ index (GET)                    │  200  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ show  (GET)                    │  200  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ new   (GET)                    │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * │ edit propre club (GET)         │  403  │  403   │  200     │  403         │  200        │  200       │  200  │
 * │ edit autre club (GET)          │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * │ transfer presidency (propre)   │  403  │  403   │  200     │  403         │  200        │  200       │  200  │
 * └────────────────────────────────┴───────┴────────┴──────────┴──────────────┴─────────────┴────────────┴───────┘
 *
 * Notes :
 * - index/show utilisent IS_AUTHENTICATED_FULLY → tout utilisateur connecté y a accès.
 * - edit propre club (PRESIDENT) → canTransferClubPresidency (PRESIDENT/ADMIN).
 * - edit autre club → canEditClub (MGMT_CTK/CN/ADMIN).
 */
final class ClubControllerTest extends AbstractWebTestCase
{
    private int $clubAId;

    private int $clubBId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);

        $clubA = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $clubB = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_B]);
        $this->assertInstanceOf(Club::class, $clubA);

        $this->clubAId = $clubA->getId();
        $this->assertInstanceOf(Club::class, $clubB);
        $this->clubBId = $clubB->getId();
    }

    // -----------------------------------------------------------------------
    // GET /club/ — index
    // -----------------------------------------------------------------------
    public function testIndexGrantedForRoleUser(): void
    {
        // IS_AUTHENTICATED_FULLY : ROLE_USER est authentifié → accès accordé
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetGranted('/club/');
    }

    public function testIndexGrantedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetGranted('/club/');
    }

    public function testIndexGrantedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/club/');
    }

    public function testIndexGrantedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetGranted('/club/');
    }

    public function testIndexGrantedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/club/');
    }

    public function testIndexGrantedForEquipmentManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/club/');
    }

    public function testIndexGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/club/');
    }

    // -----------------------------------------------------------------------
    // GET /club/{id} — show
    // -----------------------------------------------------------------------
    public function testShowGrantedForRoleUser(): void
    {
        // IS_AUTHENTICATED_FULLY : ROLE_USER est authentifié → accès accordé
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetGranted('/club/'.$this->clubAId);
    }

    public function testShowGrantedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetGranted('/club/'.$this->clubAId);
    }

    public function testShowGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/club/'.$this->clubAId);
    }

    // -----------------------------------------------------------------------
    // GET /club/new — creation
    // -----------------------------------------------------------------------
    public function testNewDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/club/new');
    }

    public function testNewDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/club/new');
    }

    public function testNewDeniedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/club/new');
    }

    public function testNewDeniedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/club/new');
    }

    public function testNewGrantedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/club/new');
    }

    public function testNewGrantedForEquipmentManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/club/new');
    }

    public function testNewGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/club/new');
    }

    // -----------------------------------------------------------------------
    // GET /club/{id}/edit — edition de son propre club (Club A, président = president@kyudo-test.fr)
    // -----------------------------------------------------------------------
    public function testEditOwnClubDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/club/'.$this->clubAId.'/edit');
    }

    public function testEditOwnClubDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/club/'.$this->clubAId.'/edit');
    }

    public function testEditOwnClubGrantedForPresident(): void
    {
        // president@kyudo-test.fr est président du Club A
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/club/'.$this->clubAId.'/edit');
    }

    public function testEditOwnClubDeniedForEquipmentManagerClub(): void
    {
        // EQUIPMENT_MANAGER_CLUB n'a pas le droit d'éditer les clubs
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/club/'.$this->clubAId.'/edit');
    }

    public function testEditOwnClubGrantedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/club/'.$this->clubAId.'/edit');
    }

    public function testEditOwnClubGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/club/'.$this->clubAId.'/edit');
    }

    // -----------------------------------------------------------------------
    // GET /club/{id}/edit — edition d'un autre club (Club B, président = personne)
    // Le président du Club A ne peut PAS éditer Club B
    // -----------------------------------------------------------------------
    public function testEditOtherClubDeniedForPresident(): void
    {
        // president@kyudo-test.fr est président du Club A, pas du Club B
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/club/'.$this->clubBId.'/edit');
    }

    public function testEditOtherClubGrantedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/club/'.$this->clubBId.'/edit');
    }

    public function testEditOtherClubGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/club/'.$this->clubBId.'/edit');
    }

    // -----------------------------------------------------------------------
    // TRANSFER_CLUB_PRESIDENCY — le président ne peut PAS éditer un autre club
    // (Les cas member/manager-club/president sur propre club sont couverts
    //  par la section testEditOwnClub* ci-dessus.)
    // -----------------------------------------------------------------------
    public function testTransferPresidencyDeniedForPresidentOnOtherClub(): void
    {
        // PRESIDENT ne peut PAS éditer un club qui n'est pas le sien
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/club/'.$this->clubBId.'/edit');
    }

    // -----------------------------------------------------------------------
    // Synchronisation des rôles lors du transfert de présidence
    // -----------------------------------------------------------------------

    /**
     * Quand l'admin change le président du Club A, l'ancien président perd
     * ROLE_CLUB_PRESIDENT et redevient ROLE_MEMBER (il est membre du club).
     * Le nouveau président gagne ROLE_CLUB_PRESIDENT.
     */
    public function testTransferPresidencyUpdatesRoles(): void
    {
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);

        $clubA          = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $oldPresident   = $userRepo->findOneBy(['email' => AppFixtures::USER_PRESIDENT]);
        $newPresident   = $userRepo->findOneBy(['email' => AppFixtures::USER_MEMBER]);
        $this->assertInstanceOf(Club::class, $clubA);
        $this->assertInstanceOf(User::class, $oldPresident);
        $this->assertInstanceOf(User::class, $newPresident);

        $this->loginAs(AppFixtures::USER_ADMIN);

        $crawler = $this->client->request('GET', '/club/'.$clubA->getId().'/edit');
        $form    = $crawler->selectButton('Enregistrer')->form();
        $form['club[president]'] = (string) $newPresident->getId();
        $this->client->submit($form);

        $this->assertResponseRedirects();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();

        // L'ancien président perd ROLE_CLUB_PRESIDENT
        $refreshedOldPresident = $userRepo->findOneBy(['email' => AppFixtures::USER_PRESIDENT]);
        $this->assertInstanceOf(User::class, $refreshedOldPresident);
        $this->assertNotContains(
            UserRole::CLUB_PRESIDENT->value,
            $refreshedOldPresident->getRoles(),
            'L\'ancien président ne doit plus avoir ROLE_CLUB_PRESIDENT.'
        );
        // Et retrouve ROLE_MEMBER (il reste membre du club)
        $this->assertContains(
            UserRole::MEMBER->value,
            $refreshedOldPresident->getRoles(),
            'L\'ancien président doit retrouver ROLE_MEMBER.'
        );

        // Le nouveau président gagne ROLE_CLUB_PRESIDENT
        $refreshedNewPresident = $userRepo->findOneBy(['email' => AppFixtures::USER_MEMBER]);
        $this->assertInstanceOf(User::class, $refreshedNewPresident);
        $this->assertContains(
            UserRole::CLUB_PRESIDENT->value,
            $refreshedNewPresident->getRoles(),
            'Le nouveau président doit avoir ROLE_CLUB_PRESIDENT.'
        );
        // Et ne cumule pas avec ROLE_EQUIPMENT_MANAGER_CLUB
        $this->assertNotContains(
            UserRole::EQUIPMENT_MANAGER_CLUB->value,
            $refreshedNewPresident->getRoles(),
            'Le nouveau président ne doit pas avoir ROLE_EQUIPMENT_MANAGER_CLUB en même temps.'
        );
    }

    // -----------------------------------------------------------------------
    // Synchronisation des rôles lors du transfert du gestionnaire matériel
    // -----------------------------------------------------------------------

    /**
     * Quand l'admin change le gestionnaire matériel du Club A, l'ancien
     * gestionnaire perd ROLE_EQUIPMENT_MANAGER_CLUB et redevient ROLE_MEMBER.
     * Le nouveau gestionnaire gagne ROLE_EQUIPMENT_MANAGER_CLUB.
     */
    public function testTransferEquipmentManagerUpdatesRoles(): void
    {
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);

        $clubA          = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $oldManager     = $userRepo->findOneBy(['email' => AppFixtures::USER_MANAGER_CLUB]);
        $newManager     = $userRepo->findOneBy(['email' => AppFixtures::USER_MEMBER]);
        $this->assertInstanceOf(Club::class, $clubA);
        $this->assertInstanceOf(User::class, $oldManager);
        $this->assertInstanceOf(User::class, $newManager);

        $this->loginAs(AppFixtures::USER_ADMIN);

        $crawler = $this->client->request('GET', '/club/'.$clubA->getId().'/edit');
        $form    = $crawler->selectButton('Enregistrer')->form();
        $form['club[equipmentManager]'] = (string) $newManager->getId();
        $this->client->submit($form);

        $this->assertResponseRedirects();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();

        // L'ancien gestionnaire perd ROLE_EQUIPMENT_MANAGER_CLUB
        $refreshedOldManager = $userRepo->findOneBy(['email' => AppFixtures::USER_MANAGER_CLUB]);
        $this->assertInstanceOf(User::class, $refreshedOldManager);
        $this->assertNotContains(
            UserRole::EQUIPMENT_MANAGER_CLUB->value,
            $refreshedOldManager->getRoles(),
            "L'ancien gestionnaire ne doit plus avoir ROLE_EQUIPMENT_MANAGER_CLUB."
        );
        $this->assertContains(
            UserRole::MEMBER->value,
            $refreshedOldManager->getRoles(),
            "L'ancien gestionnaire doit retrouver ROLE_MEMBER."
        );

        // Le nouveau gestionnaire gagne ROLE_EQUIPMENT_MANAGER_CLUB
        $refreshedNewManager = $userRepo->findOneBy(['email' => AppFixtures::USER_MEMBER]);
        $this->assertInstanceOf(User::class, $refreshedNewManager);
        $this->assertContains(
            UserRole::EQUIPMENT_MANAGER_CLUB->value,
            $refreshedNewManager->getRoles(),
            'Le nouveau gestionnaire doit avoir ROLE_EQUIPMENT_MANAGER_CLUB.'
        );
        // Et ne cumule pas avec ROLE_CLUB_PRESIDENT
        $this->assertNotContains(
            UserRole::CLUB_PRESIDENT->value,
            $refreshedNewManager->getRoles(),
            'Le nouveau gestionnaire ne doit pas avoir ROLE_CLUB_PRESIDENT en même temps.'
        );
    }

    // -----------------------------------------------------------------------
    // Validation : cumul président + gestionnaire matériel interdit
    // -----------------------------------------------------------------------

    /**
     * Le formulaire doit rejeter le cas où le même utilisateur est désigné
     * à la fois président et gestionnaire matériel d'un club.
     */
    public function testCannotCumulatePresidentAndEquipmentManagerRoles(): void
    {
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);

        $clubA    = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $userMember = $userRepo->findOneBy(['email' => AppFixtures::USER_MEMBER]);
        $this->assertInstanceOf(Club::class, $clubA);
        $this->assertInstanceOf(User::class, $userMember);

        $this->loginAs(AppFixtures::USER_ADMIN);

        $crawler = $this->client->request('GET', '/club/'.$clubA->getId().'/edit');
        $form    = $crawler->selectButton('Enregistrer')->form();

        // Même utilisateur pour les deux rôles
        $form['club[president]']        = (string) $userMember->getId();
        $form['club[equipmentManager]'] = (string) $userMember->getId();
        $this->client->submit($form);

        // Le formulaire doit être ré-affiché avec une erreur de validation (422 Unprocessable Entity)
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains(
            'body',
            'Un utilisateur ne peut pas être à la fois président et gestionnaire matériel'
        );
    }
}
