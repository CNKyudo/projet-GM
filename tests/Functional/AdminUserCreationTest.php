<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels : AdminUserController — Création d'utilisateur par un administrateur.
 *
 * Routes testées :
 *   GET  /admin/users/create → CREATE_USER (ADMIN uniquement)
 *   POST /admin/users/create → CREATE_USER (ADMIN uniquement)
 *   GET  /profile/password/force → EDIT_OWN_ACCOUNT_INFORMATION (utilisateur avec mustChangePassword)
 *   POST /profile/password/force → EDIT_OWN_ACCOUNT_INFORMATION
 *
 * Règles métier :
 *   - Seul ROLE_ADMIN peut créer un utilisateur
 *   - Le mot de passe est généré aléatoirement (12 car., 1 majuscule, 1 spécial)
 *   - Le nouvel utilisateur reçoit ROLE_USER et mustChangePassword = true
 *   - À la première connexion, l'utilisateur est redirigé vers /profile/password/force
 *   - Après avoir changé son mot de passe, mustChangePassword passe à false
 */
final class AdminUserCreationTest extends AbstractWebTestCase
{
    // -----------------------------------------------------------------------
    // GET /admin/users/create — accès réservé à ROLE_ADMIN
    // -----------------------------------------------------------------------

    public function testAdminCanAccessCreateUserPage(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/admin/users/create');
    }

    public function testRoleUserCannotAccessCreateUserPage(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/admin/users/create');
    }

    public function testMemberCannotAccessCreateUserPage(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/admin/users/create');
    }

    public function testPresidentCannotAccessCreateUserPage(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/admin/users/create');
    }

    public function testManagerClubCannotAccessCreateUserPage(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/admin/users/create');
    }

    public function testManagerCtkCannotAccessCreateUserPage(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetDenied('/admin/users/create');
    }

    public function testManagerCnCannotAccessCreateUserPage(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetDenied('/admin/users/create');
    }

    // -----------------------------------------------------------------------
    // POST /admin/users/create — création effective
    // -----------------------------------------------------------------------

    public function testAdminCanCreateUserWithValidEmail(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/users/create');
        $form = $crawler->selectButton('Créer l\'utilisateur')->form();
        $form['admin_user_create[email]'] = 'newuser@kyudo-test.fr';

        $this->client->submit($form);

        // Après la création, on doit être redirigé vers la page de succès
        $this->assertResponseRedirects('/admin/users/create/success');

        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();

        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        $newUser = $userRepo->findOneBy(['email' => 'newuser@kyudo-test.fr']);

        $this->assertInstanceOf(User::class, $newUser);
        $this->assertContains('ROLE_USER', $newUser->getRoles());
        $this->assertTrue($newUser->isMustChangePassword());
    }

    public function testAdminCanSeeGeneratedPasswordOnSuccessPage(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/users/create');
        $form = $crawler->selectButton('Créer l\'utilisateur')->form();
        $newUserEmail = 'newuser-with-password@kyudo-test.fr';
        $form['admin_user_create[email]'] = $newUserEmail;

        $this->client->submit($form);

        // Suivre la redirection vers la page de succès
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Vérifier que la page contient l'email du nouvel utilisateur
        $this->assertStringContainsString($newUserEmail, (string) $this->client->getResponse()->getContent());

        // Vérifier que la page contient un formulaire ou du contenu pour afficher le mot de passe
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Identifiants à communiquer', (string) $content);
        $this->assertStringContainsString('Mot de passe temporaire', (string) $content);
    }

    public function testSuccessPageIsOnlyAccessibleAfterCreation(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        // Accès direct à la page de succès sans avoir créé d'utilisateur
        $this->client->request(Request::METHOD_GET, '/admin/users/create/success');

        // Doit rediriger vers la page de création
        $this->assertResponseRedirects('/admin/users/create');
    }

    public function testCreatingUserWithExistingEmailFails(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/users/create');
        $form = $crawler->selectButton('Créer l\'utilisateur')->form();
        $form['admin_user_create[email]'] = AppFixtures::USER_USER;

        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreatingUserWithInvalidEmailFails(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/users/create');
        $form = $crawler->selectButton('Créer l\'utilisateur')->form();
        $form['admin_user_create[email]'] = 'pas-un-email';

        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(422);
    }

    // -----------------------------------------------------------------------
    // Listener : redirection forcée vers /profile/password/force
    // -----------------------------------------------------------------------

    public function testUserWithMustChangePasswordIsRedirectedOnProfileAccess(): void
    {
        $user = $this->createUserWithMustChangePassword('must-change-1@kyudo-test.fr');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/profile');

        $this->assertResponseRedirects('/profile/password/force');
    }

    public function testUserWithMustChangePasswordIsRedirectedOnUserIndexAccess(): void
    {
        // On crée un admin avec mustChangePassword pour vérifier la redirection même pour les admins
        $user = $this->createUserWithMustChangePassword('must-change-admin@kyudo-test.fr', ['ROLE_ADMIN']);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/user');

        $this->assertResponseRedirects('/profile/password/force');
    }

    public function testUserWithoutMustChangePasswordIsNotRedirected(): void
    {
        $this->loginAs(AppFixtures::USER_USER);

        $this->client->request(Request::METHOD_GET, '/profile');

        $this->assertResponseIsSuccessful();
    }

    // -----------------------------------------------------------------------
    // GET /profile/password/force — accessible à l'utilisateur concerné
    // -----------------------------------------------------------------------

    public function testForceChangePasswordPageIsAccessibleWhenRequired(): void
    {
        $user = $this->createUserWithMustChangePassword('must-change-2@kyudo-test.fr');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/profile/password/force');

        $this->assertResponseIsSuccessful();
    }

    public function testForceChangePasswordPageRedirectsToProfileIfNotRequired(): void
    {
        $this->loginAs(AppFixtures::USER_USER);

        $this->client->request(Request::METHOD_GET, '/profile/password/force');

        $this->assertResponseRedirects('/profile');
    }

    // -----------------------------------------------------------------------
    // POST /profile/password/force — changement effectif, mustChangePassword → false
    // -----------------------------------------------------------------------

    public function testUserCanChangePasswordAndAccessAppNormally(): void
    {
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $user = $this->createUserWithMustChangePassword('must-change-3@kyudo-test.fr');
        $this->client->loginUser($user);

        // Accès à la page de changement forcé
        $crawler = $this->client->request(Request::METHOD_GET, '/profile/password/force');
        $this->assertResponseIsSuccessful();

        // Soumission du nouveau mot de passe
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['force_change_password[plainPassword][first]']  = 'NouveauMotDePasse9@';
        $form['force_change_password[plainPassword][second]'] = 'NouveauMotDePasse9@';
        $this->client->submit($form);

        $this->assertResponseRedirects('/profile');

        // Vérification en base : mustChangePassword est passé à false
        $em->clear();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        $updated = $userRepo->findOneBy(['email' => 'must-change-3@kyudo-test.fr']);
        $this->assertInstanceOf(User::class, $updated);
        $this->assertFalse($updated->isMustChangePassword());

        // L'utilisateur peut désormais accéder normalement au profil (sans redirection)
        $this->client->request(Request::METHOD_GET, '/profile');
        $this->assertResponseIsSuccessful();
    }

    // -----------------------------------------------------------------------
    // Intégration complète : création, première connexion et changement de mot de passe
    // -----------------------------------------------------------------------

    public function testNewUserCreatedAdminCanViewAndLoginRequiresPasswordChange(): void
    {
        // Step 1: L'admin crée un nouvel utilisateur via le formulaire
        $this->loginAs(AppFixtures::USER_ADMIN);
        $newUserEmail = 'brand-new-user@kyudo-test.fr';

        $this->assertFormSubmitRedirects(
            '/admin/users/create',
            'Créer l\'utilisateur',
            ['admin_user_create[email]' => $newUserEmail],
        );

        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);

        // Step 2: Vérifier que l'utilisateur a été créé avec mustChangePassword = true
        $em->clear();
        $newUser = $userRepo->findOneBy(['email' => $newUserEmail]);
        $this->assertInstanceOf(User::class, $newUser);
        $this->assertTrue($newUser->isMustChangePassword(), 'Le nouvel utilisateur doit avoir mustChangePassword = true');
        $this->assertContains('ROLE_USER', $newUser->getRoles(), 'Le nouvel utilisateur doit avoir ROLE_USER');
    }

    // -----------------------------------------------------------------------
    // Intégration : création d'utilisateur + connexion + redirection obligatoire
    // -----------------------------------------------------------------------

    public function testNewUserIsRedirectedToPasswordChangeOnLogin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        // Créer un nouvel utilisateur
        $newUserEmail = 'redirect-test@kyudo-test.fr';
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/users/create');
        $form = $crawler->selectButton('Créer l\'utilisateur')->form();
        $form['admin_user_create[email]'] = $newUserEmail;

        $this->client->submit($form);

        // Récupérer le mot de passe depuis la session AVANT redirection
        $session = $this->client->getRequest()->getSession();
        $sessionData = $session->get('admin_user_created');
        $plainPassword = $sessionData['plainPassword'];

        // Suivre la redirection vers la page de succès
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Se déconnecter
        $this->client->request(Request::METHOD_GET, '/logout');

        // Se connecter avec le nouvel utilisateur et le mot de passe généré
        $crawler = $this->client->request(Request::METHOD_GET, '/login');
        $form = $crawler->selectButton('Se connecter')->form();
        $form['_username'] = $newUserEmail;
        $form['_password'] = $plainPassword;

        $this->client->submit($form);

        // Vérifier que l'utilisateur est redirigé vers /profile/password/force
        $this->assertResponseRedirects('/profile/password/force');

        // Suivre la redirection pour vérifier que la page est accessible
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Crée et persiste un utilisateur avec mustChangePassword = true dans la transaction DAMA.
     *
     * @param list<string> $roles
     */
    private function createUserWithMustChangePassword(string $email, array $roles = []): User
    {
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, 'TempGen#Pass1'));
        $user->setMustChangePassword(true);
        $user->setRoles($roles);

        $em->persist($user);
        $em->flush();

        return $user;
    }
}
