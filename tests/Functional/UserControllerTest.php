<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Club;
use App\Entity\Region;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\ClubRepository;
use App\Repository\RegionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels : UserController.
 *
 * Routes testées :
 *   GET  /profile              → EDIT_OWN_ACCOUNT_INFORMATION (tout utilisateur authentifié)
 *   GET  /profile/edit         → EDIT_OWN_ACCOUNT_INFORMATION
 *   GET  /profile/password     → EDIT_OWN_ACCOUNT_INFORMATION
 *   GET  /user                 → ACCESS_USER_MANAGEMENT (CLUB_PRESIDENT et au-dessus)
 *   GET  /user/{id}/club       → ASSIGN_USER_TO_ANY_CLUB
 *   GET  /user/{id}/role       → ASSIGN_USER_ROLE (CLUB_PRESIDENT et au-dessus)
 *
 * Matrice des droits attendus :
 * ┌──────────────────────────┬───────┬────────┬──────────┬──────────────┬─────────────┬────────────┬───────┐
 * │ Action                   │ USER  │ MEMBER │ PRESIDENT│ MGMT_CLUB    │ MGMT_CTK    │ MGMT_CN    │ ADMIN │
 * ├──────────────────────────┼───────┼────────┼──────────┼──────────────┼─────────────┼────────────┼───────┤
 * │ /profile (GET)           │  200  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ /profile/edit (GET)      │  200  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ /profile/password (GET)  │  200  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ /user (GET)              │  403  │  403   │  200     │  200         │  200        │  200       │  200  │
 * │ /user/{id}/club (GET)    │  403  │  403   │  200     │  200         │  200        │  200       │  200  │
 * │ /user/{id}/role (GET)    │  403  │  403   │  200     │  200         │  200        │  200       │  200  │
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

    public function testUserIndexGrantedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/user');
    }

    public function testUserIndexGrantedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetGranted('/user');
    }

    public function testUserIndexGrantedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/user');
    }

    public function testUserIndexGrantedForEquipmentManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/user');
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

    public function testAssignClubGrantedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetGranted('/user/'.$this->anyUserId.'/club');
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

    // -----------------------------------------------------------------------
    // GET /user/{id}/role — affectation de rôle
    // Autorisé pour : CLUB_PRESIDENT, EQUIPMENT_MANAGER_CLUB, CTK, CN, ADMIN
    // -----------------------------------------------------------------------
    public function testAssignRoleDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/user/'.$this->anyUserId.'/role');
    }

    public function testAssignRoleDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/user/'.$this->anyUserId.'/role');
    }

    public function testAssignRoleGrantedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/user/'.$this->anyUserId.'/role');
    }

    public function testAssignRoleGrantedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetGranted('/user/'.$this->anyUserId.'/role');
    }

    public function testAssignRoleGrantedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/user/'.$this->anyUserId.'/role');
    }

    public function testAssignRoleGrantedForEquipmentManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/user/'.$this->anyUserId.'/role');
    }

    public function testAssignRoleGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/user/'.$this->anyUserId.'/role');
    }

    // -----------------------------------------------------------------------
    // POST /user/{id}/role — intégration : l'admin assigne des rôles
    // -----------------------------------------------------------------------

    /**
     * Admin assigne ROLE_MEMBER → pas de club, rôle correctement persisté.
     */
    public function testAdminAssignsMemberRole(): void
    {
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);

        $target = $userRepo->findOneBy(['email' => AppFixtures::USER_USER]);
        $this->assertInstanceOf(User::class, $target);

        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertPostRedirects('/user/'.$target->getId().'/role', [
            'user_role_assign' => [
                'newRole' => UserRole::MEMBER->value,
            ],
        ]);

        // Vérification en base
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();

        $updated = $userRepo->findOneBy(['email' => AppFixtures::USER_USER]);
        $this->assertInstanceOf(User::class, $updated);
        $this->assertContains(UserRole::MEMBER->value, $updated->getRoles());
    }

    /**
     * Admin assigne ROLE_CLUB_PRESIDENT avec Club A → Club A a un nouveau président.
     */
    public function testAdminAssignsPresidentRole(): void
    {
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);

        $target = $userRepo->findOneBy(['email' => AppFixtures::USER_USER]);
        $clubA   = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(User::class, $target);
        $this->assertInstanceOf(Club::class, $clubA);

        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertPostRedirects('/user/'.$target->getId().'/role', [
            'user_role_assign' => [
                'newRole' => UserRole::CLUB_PRESIDENT->value,
                'club'    => (string) $clubA->getId(),
            ],
        ]);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();

        $updatedClub = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(Club::class, $updatedClub);
        $this->assertInstanceOf(User::class, $updatedClub->getPresident());
        $this->assertSame($target->getId(), $updatedClub->getPresident()->getId());
    }

    /**
     * Admin assigne ROLE_EQUIPMENT_MANAGER_CLUB avec Club A → Club A a un nouveau gestionnaire.
     */
    public function testAdminAssignsEquipmentManagerClubRole(): void
    {
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);

        $target = $userRepo->findOneBy(['email' => AppFixtures::USER_USER]);
        $clubA   = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(User::class, $target);
        $this->assertInstanceOf(Club::class, $clubA);

        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertPostRedirects('/user/'.$target->getId().'/role', [
            'user_role_assign' => [
                'newRole' => UserRole::EQUIPMENT_MANAGER_CLUB->value,
                'club'    => (string) $clubA->getId(),
            ],
        ]);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();

        $updatedClub = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(Club::class, $updatedClub);
        $this->assertInstanceOf(User::class, $updatedClub->getEquipmentManager());
        $this->assertSame($target->getId(), $updatedClub->getEquipmentManager()->getId());
    }

    /**
     * Admin assigne ROLE_EQUIPMENT_MANAGER_CTK avec Région A → managedRegions contient Région A.
     */
    public function testAdminAssignsEquipmentManagerCtkRole(): void
    {
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        /** @var RegionRepository $regionRepo */
        $regionRepo = $container->get(RegionRepository::class);

        $target  = $userRepo->findOneBy(['email' => AppFixtures::USER_USER]);
        $regionA = $regionRepo->findOneBy(['name' => AppFixtures::REGION_A]);
        $this->assertInstanceOf(User::class, $target);
        $this->assertInstanceOf(Region::class, $regionA);

        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertPostRedirects('/user/'.$target->getId().'/role', [
            'user_role_assign' => [
                'newRole'        => UserRole::EQUIPMENT_MANAGER_CTK->value,
                'managedRegions' => [(string) $regionA->getId()],
            ],
        ]);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();

        $updated = $userRepo->findOneBy(['email' => AppFixtures::USER_USER]);
        $this->assertInstanceOf(User::class, $updated);
        $this->assertContains(UserRole::EQUIPMENT_MANAGER_CTK->value, $updated->getRoles());
        $regionIds = array_map(
            static fn (Region $r): ?int => $r->getId(),
            $updated->getManagedRegions()->toArray()
        );
        $this->assertContains($regionA->getId(), $regionIds);
    }

    /**
     * Admin assigne ROLE_EQUIPMENT_MANAGER_CN → rôle CN, pas d'association de club/région.
     */
    public function testAdminAssignsEquipmentManagerCnRole(): void
    {
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);

        $target = $userRepo->findOneBy(['email' => AppFixtures::USER_USER]);
        $this->assertInstanceOf(User::class, $target);

        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertPostRedirects('/user/'.$target->getId().'/role', [
            'user_role_assign' => [
                'newRole' => UserRole::EQUIPMENT_MANAGER_CN->value,
            ],
        ]);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();

        $updated = $userRepo->findOneBy(['email' => AppFixtures::USER_USER]);
        $this->assertInstanceOf(User::class, $updated);
        $this->assertContains(UserRole::EQUIPMENT_MANAGER_CN->value, $updated->getRoles());
    }

    // -----------------------------------------------------------------------
    // Nettoyage des anciennes affectations lors d'un changement de rôle
    // -----------------------------------------------------------------------

    /**
     * Quand le président de Club A est rétrogradé en MEMBER,
     * le champ president de Club A doit être mis à null.
     */
    public function testOldPresidentAssignmentClearedOnRoleChange(): void
    {
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);

        $president = $userRepo->findOneBy(['email' => AppFixtures::USER_PRESIDENT]);
        $clubA     = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(User::class, $president);
        $this->assertInstanceOf(Club::class, $clubA);
        $this->assertSame($president->getId(), $clubA->getPresident()?->getId(), 'Club A doit avoir un président avant le test');

        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertPostRedirects('/user/'.$president->getId().'/role', [
            'user_role_assign' => [
                'newRole' => UserRole::MEMBER->value,
            ],
        ]);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();

        $updatedClub = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(Club::class, $updatedClub);
        $this->assertNotInstanceOf(User::class, $updatedClub->getPresident(), 'Le président de Club A doit être null après rétrogradation');
    }
}
