<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Club;
use App\DataFixtures\AppFixtures;
use App\Repository\ClubRepository;

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
    // TRANSFER_CLUB_PRESIDENCY — édition du propre club par le président
    // CSV : un président peut transférer sa présidence à un autre membre de son club
    // Même route que edit, mais logique distincte : voteOnClubAttribute délègue à
    // canTransferClubPresidency (PRESIDENT/ADMIN) quand le sujet est le propre club.
    // -----------------------------------------------------------------------
    public function testTransferPresidencyDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/club/'.$this->clubAId.'/edit');
    }

    public function testTransferPresidencyDeniedForEquipmentManagerClub(): void
    {
        // MGMT_CLUB n'a pas de droit de modifier un club
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/club/'.$this->clubAId.'/edit');
    }

    public function testTransferPresidencyGrantedForPresident(): void
    {
        // CSV : PRESIDENT peut transférer sa présidence → edit de son propre club autorisé
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/club/'.$this->clubAId.'/edit');
    }

    public function testTransferPresidencyDeniedForPresidentOnOtherClub(): void
    {
        // PRESIDENT ne peut PAS éditer un club qui n'est pas le sien
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/club/'.$this->clubBId.'/edit');
    }
}
