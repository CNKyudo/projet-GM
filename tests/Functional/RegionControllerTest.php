<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Region;
use App\DataFixtures\AppFixtures;
use App\Repository\RegionRepository;

/**
 * Tests fonctionnels : RegionController.
 *
 * Toutes les routes /region/* sont protégées par #[IsGranted('ROLE_ADMIN')] au niveau classe.
 *
 * Routes testées :
 *   GET  /region/          → ROLE_ADMIN uniquement
 *   GET  /region/new       → ROLE_ADMIN uniquement
 *   GET  /region/{id}      → ROLE_ADMIN uniquement
 *   GET  /region/{id}/edit → ROLE_ADMIN uniquement
 *   POST /region/{id}      → ROLE_ADMIN uniquement (delete)
 *
 * ┌────────────────────────────┬───────┬────────┬──────────┬──────────────┬─────────────┬────────────┬───────┐
 * │ Action                     │ USER  │ MEMBER │ PRESIDENT│ MGMT_CLUB    │ MGMT_CTK    │ MGMT_CN    │ ADMIN │
 * ├────────────────────────────┼───────┼────────┼──────────┼──────────────┼─────────────┼────────────┼───────┤
 * │ index (GET)                │  403  │  403   │  403     │  403         │  403        │  403       │  200  │
 * │ new   (GET)                │  403  │  403   │  403     │  403         │  403        │  403       │  200  │
 * │ show  (GET)                │  403  │  403   │  403     │  403         │  403        │  403       │  200  │
 * │ edit  (GET)                │  403  │  403   │  403     │  403         │  403        │  403       │  200  │
 * │ delete (POST)              │  403  │  403   │  403     │  403         │  403        │  403       │  302  │
 * └────────────────────────────┴───────┴────────┴──────────┴──────────────┴─────────────┴────────────┴───────┘
 */
final class RegionControllerTest extends AbstractWebTestCase
{
    private int $regionAId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();
        /** @var RegionRepository $regionRepo */
        $regionRepo = $container->get(RegionRepository::class);

        $regionA = $regionRepo->findOneBy(['name' => AppFixtures::REGION_A]);
        $this->assertInstanceOf(Region::class, $regionA);
        $this->regionAId = $regionA->getId();
    }

    // -----------------------------------------------------------------------
    // GET /region/ — index
    // -----------------------------------------------------------------------
    public function testIndexDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/region/');
    }

    public function testIndexDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/region/');
    }

    public function testIndexDeniedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/region/');
    }

    public function testIndexDeniedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/region/');
    }

    public function testIndexDeniedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetDenied('/region/');
    }

    public function testIndexDeniedForEquipmentManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetDenied('/region/');
    }

    public function testIndexGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/region/');
    }

    // -----------------------------------------------------------------------
    // GET /region/new — création
    // -----------------------------------------------------------------------
    public function testNewDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/region/new');
    }

    public function testNewDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/region/new');
    }

    public function testNewDeniedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/region/new');
    }

    public function testNewDeniedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/region/new');
    }

    public function testNewDeniedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetDenied('/region/new');
    }

    public function testNewDeniedForEquipmentManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetDenied('/region/new');
    }

    public function testNewGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/region/new');
    }

    // -----------------------------------------------------------------------
    // GET /region/{id} — show
    // -----------------------------------------------------------------------
    public function testShowDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/region/'.$this->regionAId);
    }

    public function testShowDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/region/'.$this->regionAId);
    }

    public function testShowDeniedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetDenied('/region/'.$this->regionAId);
    }

    public function testShowGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/region/'.$this->regionAId);
    }

    // -----------------------------------------------------------------------
    // GET /region/{id}/edit — édition
    // -----------------------------------------------------------------------
    public function testEditDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/region/'.$this->regionAId.'/edit');
    }

    public function testEditDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/region/'.$this->regionAId.'/edit');
    }

    public function testEditDeniedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/region/'.$this->regionAId.'/edit');
    }

    public function testEditDeniedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetDenied('/region/'.$this->regionAId.'/edit');
    }

    public function testEditDeniedForEquipmentManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetDenied('/region/'.$this->regionAId.'/edit');
    }

    public function testEditGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/region/'.$this->regionAId.'/edit');
    }

    // -----------------------------------------------------------------------
    // POST /region/{id} — suppression
    // -----------------------------------------------------------------------
    public function testDeleteDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertPostDenied(
            '/region/'.$this->regionAId,
            ['_token' => 'invalid'],
        );
    }

    public function testDeleteDeniedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertPostDenied(
            '/region/'.$this->regionAId,
            ['_token' => 'invalid'],
        );
    }

    public function testDeleteGrantedForAdmin(): void
    {
        // On utilise un token invalide : Symfony redirige quand même (pas de vrai delete)
        // mais le contrôleur laisse passer (200/302) car la vérif CSRF est dans le corps
        // et l'accès est accordé au niveau sécurité.
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertPostRedirects(
            '/region/'.$this->regionAId,
            ['_token' => 'invalid-but-access-granted'],
        );
    }
}
