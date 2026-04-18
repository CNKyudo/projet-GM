<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use App\DataFixtures\AppFixtures;

/**
 * Tests fonctionnels : UserController.
 *
 * Routes testées :
 *   GET  /profile              → EDIT_OWN_ACCOUNT_INFORMATION (tout utilisateur authentifié)
 *   GET  /profile/edit         → EDIT_OWN_ACCOUNT_INFORMATION
 *   GET  /profile/password     → EDIT_OWN_ACCOUNT_INFORMATION
 *   GET  /user                 → ACCESS_USER_MANAGEMENT (ADMIN uniquement)
 *   GET  /user/{id}/club       → ASSIGN_USER_TO_ANY_CLUB
 *
 * Matrice des droits attendus :
 * ┌──────────────────────────┬───────┬────────┬──────────┬──────────────┬─────────────┬────────────┬───────┐
 * │ Action                   │ USER  │ MEMBER │ PRESIDENT│ MGMT_CLUB    │ MGMT_CTK    │ MGMT_CN    │ ADMIN │
 * ├──────────────────────────┼───────┼────────┼──────────┼──────────────┼─────────────┼────────────┼───────┤
 * │ /profile (GET)           │  200  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ /profile/edit (GET)      │  200  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ /profile/password (GET)  │  200  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ /user (GET)              │  403  │  403   │  403     │  403         │  403        │  403       │  200  │
 * │ /user/{id}/club (GET)    │  403  │  403   │  200     │  403         │  200        │  200       │  200  │
 * └──────────────────────────┴───────┴────────┴──────────┴──────────────┴─────────────┴────────────┴───────┘
 */
final class UserControllerTest extends AbstractWebTestCase
{
    private int $anyUserId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();
        /** @var UserRepository $repo */
        $repo = $container->get(UserRepository::class);

        // On utilise l'utilisateur USER_USER comme cible pour assign_club
        $user = $repo->findOneBy(['email' => AppFixtures::USER_USER]);
        $this->assertInstanceOf(User::class, $user);
        $this->anyUserId = $user->getId();
    }

    // -----------------------------------------------------------------------
    // GET /profile — profil (accessible à tous les authentifiés)
    // -----------------------------------------------------------------------
    public function testProfileGrantedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetGranted('/profile');
    }

    public function testProfileGrantedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetGranted('/profile');
    }

    public function testProfileGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/profile');
    }

    // -----------------------------------------------------------------------
    // GET /profile/edit — édition du profil (accessible à tous)
    // -----------------------------------------------------------------------
    public function testProfileEditGrantedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetGranted('/profile/edit');
    }

    public function testProfileEditGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/profile/edit');
    }

    // -----------------------------------------------------------------------
    // GET /profile/password — changement de mot de passe (accessible à tous)
    // -----------------------------------------------------------------------
    public function testChangePasswordGrantedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetGranted('/profile/password');
    }

    public function testChangePasswordGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/profile/password');
    }

    // -----------------------------------------------------------------------
    // GET /user — gestion des utilisateurs (ADMIN uniquement)
    // -----------------------------------------------------------------------
    public function testUserIndexDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/user');
    }

    public function testUserIndexDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/user');
    }

    public function testUserIndexDeniedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/user');
    }

    public function testUserIndexDeniedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetDenied('/user');
    }

    public function testUserIndexGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/user');
    }

    // -----------------------------------------------------------------------
    // GET /user/{id}/club — assignation d'un club
    // Autorisé pour : CLUB_PRESIDENT, EQUIPMENT_MANAGER_CTK, ADMIN
    // -----------------------------------------------------------------------
    public function testAssignClubDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/user/'.$this->anyUserId.'/club');
    }

    public function testAssignClubDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/user/'.$this->anyUserId.'/club');
    }

    public function testAssignClubGrantedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/user/'.$this->anyUserId.'/club');
    }

    public function testAssignClubDeniedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/user/'.$this->anyUserId.'/club');
    }

    public function testAssignClubGrantedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/user/'.$this->anyUserId.'/club');
    }

    public function testAssignClubGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/user/'.$this->anyUserId.'/club');
    }
}
