<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Club;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\ClubRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests d'intégration pour le formulaire UserClubAssignType et
 * le contrôleur UserClubAssignController::assignClub.
 *
 * Ces tests valident que les opérations de réattribution de club
 * synchronisent correctement les rôles et les associations Club↔User.
 */
final class UserClubAssignIntegrationTest extends AbstractWebTestCase
{
    /**
     * Scénario 1 — Réattribution de présidence entre deux présidents.
     *
     * User A est président du Club A, User B est président du Club B.
     * On attribue User B comme président du Club A.
     *
     * Résultat attendu :
     *   - User B devient président du Club A et conserve ROLE_CLUB_PRESIDENT
     *   - User B est détaché du Club B
     *   - User A perd ROLE_CLUB_PRESIDENT (remplacé par ROLE_MEMBER)
     *   - Le Club B n'a plus de président
     */
    public function testPresidentReassignmentDetachesOldClub(): void
    {
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);

        $clubA = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $clubB = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_B]);
        $this->assertInstanceOf(Club::class, $clubA);
        $this->assertInstanceOf(Club::class, $clubB);

        $userPresidentA = $userRepo->findOneBy(['email' => AppFixtures::USER_PRESIDENT]);
        $userPresidentB = $userRepo->findOneBy(['email' => 'president.lyon@kyudo.fr']);
        $this->assertInstanceOf(User::class, $userPresidentA);
        $this->assertInstanceOf(User::class, $userPresidentB);

        // État initial
        $this->assertInstanceOf(User::class, $clubA->getPresident());
        $this->assertSame($userPresidentA->getId(), $clubA->getPresident()->getId());
        $this->assertInstanceOf(User::class, $clubB->getPresident());
        $this->assertSame($userPresidentB->getId(), $clubB->getPresident()->getId());
        $this->assertContains(UserRole::CLUB_PRESIDENT->value, $userPresidentA->getRoles());
        $this->assertContains(UserRole::CLUB_PRESIDENT->value, $userPresidentB->getRoles());

        // Action : attribuer User B comme président du Club A
        $this->loginAs(AppFixtures::USER_ADMIN);

        $crawler = $this->client->request(Request::METHOD_GET, '/user/'.$userPresidentB->getId().'/club');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['user_club_assign[clubWhichImPresidentOf]'] = (string) $clubA->getId();
        $form['user_club_assign[clubWhereImEquipmentManager]'] = '';
        $this->client->submit($form);

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertSame(302, $statusCode, 'POST should redirect on success (got '.$statusCode.')');

        // Rafraîchir les entités
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();

        $updatedClubA = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $updatedClubB = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_B]);
        $updatedPresidentA = $userRepo->findOneBy(['email' => AppFixtures::USER_PRESIDENT]);
        $updatedPresidentB = $userRepo->findOneBy(['email' => 'president.lyon@kyudo.fr']);

        $this->assertInstanceOf(Club::class, $updatedClubA);
        $this->assertInstanceOf(Club::class, $updatedClubB);
        $this->assertInstanceOf(User::class, $updatedPresidentA);
        $this->assertInstanceOf(User::class, $updatedPresidentB);

        // User B est maintenant président du Club A
        $this->assertInstanceOf(User::class, $updatedClubA->getPresident(), 'Club A should have a president');
        $this->assertSame($updatedPresidentB->getId(), $updatedClubA->getPresident()->getId(), 'User B should be president of Club A');

        // User B est détaché du Club B
        $this->assertNotInstanceOf(User::class, $updatedClubB->getPresident(), 'Club B should have no president');

        // User B conserve ROLE_CLUB_PRESIDENT
        $this->assertContains(UserRole::CLUB_PRESIDENT->value, $updatedPresidentB->getRoles(), 'User B must keep CLUB_PRESIDENT role');

        // User A perd ROLE_CLUB_PRESIDENT
        $this->assertNotContains(UserRole::CLUB_PRESIDENT->value, $updatedPresidentA->getRoles(), 'User A must lose CLUB_PRESIDENT role');
    }

    /**
     * Scénario 2 — Un gestionnaire matériel devient président.
     *
     * User A est président du Club A, User B est gestionnaire matériel du Club B.
     * On attribue User B comme président du Club A.
     *
     * Résultat attendu :
     *   - User B devient président du Club A, perd ROLE_EQUIPMENT_MANAGER_CLUB,
     *     gagne ROLE_CLUB_PRESIDENT, et est détaché du Club B
     *   - User A perd ROLE_CLUB_PRESIDENT
     *   - Le Club B n'a plus de gestionnaire matériel
     */
    public function testManagerBecomingPresidentLosesManagerRole(): void
    {
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);

        $clubA = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $clubB = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_B]);
        $this->assertInstanceOf(Club::class, $clubA);
        $this->assertInstanceOf(Club::class, $clubB);

        $userPresidentA = $userRepo->findOneBy(['email' => AppFixtures::USER_PRESIDENT]);
        $userManagerB = $userRepo->findOneBy(['email' => 'manager.lyon@kyudo.fr']);
        $this->assertInstanceOf(User::class, $userPresidentA);
        $this->assertInstanceOf(User::class, $userManagerB);

        // État initial
        $this->assertInstanceOf(User::class, $clubA->getPresident());
        $this->assertSame($userPresidentA->getId(), $clubA->getPresident()->getId());
        $this->assertInstanceOf(User::class, $clubB->getEquipmentManager());
        $this->assertSame($userManagerB->getId(), $clubB->getEquipmentManager()->getId());
        $this->assertContains(UserRole::CLUB_PRESIDENT->value, $userPresidentA->getRoles());
        $this->assertContains(UserRole::EQUIPMENT_MANAGER_CLUB->value, $userManagerB->getRoles());

        // Action : attribuer User B (manager B) comme président du Club A
        $this->loginAs(AppFixtures::USER_ADMIN);

        $crawler = $this->client->request(Request::METHOD_GET, '/user/'.$userManagerB->getId().'/club');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['user_club_assign[clubWhichImPresidentOf]'] = (string) $clubA->getId();
        $form['user_club_assign[clubWhereImEquipmentManager]'] = '';
        $this->client->submit($form);

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertSame(302, $statusCode, 'POST should redirect on success (got '.$statusCode.')');

        // Rafraîchir les entités
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();

        $updatedClubA = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $updatedClubB = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_B]);
        $updatedPresidentA = $userRepo->findOneBy(['email' => AppFixtures::USER_PRESIDENT]);
        $updatedManagerB = $userRepo->findOneBy(['email' => 'manager.lyon@kyudo.fr']);

        $this->assertInstanceOf(Club::class, $updatedClubA);
        $this->assertInstanceOf(Club::class, $updatedClubB);
        $this->assertInstanceOf(User::class, $updatedPresidentA);
        $this->assertInstanceOf(User::class, $updatedManagerB);

        // User B est maintenant président du Club A
        $this->assertInstanceOf(User::class, $updatedClubA->getPresident(), 'Club A should have a president');
        $this->assertSame($updatedManagerB->getId(), $updatedClubA->getPresident()->getId(), 'User B should be president of Club A');

        // User B n'est plus gestionnaire du Club B
        $this->assertNotInstanceOf(User::class, $updatedClubB->getEquipmentManager(), 'Club B should have no equipment manager');

        // User B a gagné ROLE_CLUB_PRESIDENT
        $this->assertContains(UserRole::CLUB_PRESIDENT->value, $updatedManagerB->getRoles(), 'User B must gain CLUB_PRESIDENT role');

        // User B a perdu ROLE_EQUIPMENT_MANAGER_CLUB
        $this->assertNotContains(UserRole::EQUIPMENT_MANAGER_CLUB->value, $updatedManagerB->getRoles(), 'User B must lose EQUIPMENT_MANAGER_CLUB role');

        // User A perd ROLE_CLUB_PRESIDENT
        $this->assertNotContains(UserRole::CLUB_PRESIDENT->value, $updatedPresidentA->getRoles(), 'User A must lose CLUB_PRESIDENT role');
    }

    /**
     * Scénario 3 — Un utilisateur ne peut pas être à la fois président
     * et gestionnaire matériel du même club (validation POST_SUBMIT).
     *
     * User A est président du Club A. On essaie de le nommer également
     * gestionnaire matériel du Club A dans le même formulaire.
     *
     * Résultat attendu : le formulaire n'est pas valide (HTTP 422)
     * et contient une erreur de validation.
     */
    public function testPresidentCannotAlsoBeManagerOfSameClub(): void
    {
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);

        $clubA = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(Club::class, $clubA);

        $userPresidentA = $userRepo->findOneBy(['email' => AppFixtures::USER_PRESIDENT]);
        $this->assertInstanceOf(User::class, $userPresidentA);

        // Action : essayer d'attribuer Club A comme président ET gestionnaire
        $this->loginAs(AppFixtures::USER_ADMIN);

        $crawler = $this->client->request(Request::METHOD_GET, '/user/'.$userPresidentA->getId().'/club');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['user_club_assign[clubWhichImPresidentOf]'] = (string) $clubA->getId();
        $form['user_club_assign[clubWhereImEquipmentManager]'] = (string) $clubA->getId();
        $this->client->submit($form);

        // Le formulaire doit rester sur la page (erreur de validation) — Symfony 7 retourne 422 pour les formulaires invalides
        $this->assertSame(422, $this->client->getResponse()->getStatusCode(), 'Form should return 422 when invalid');

        // Vérifier que le formulaire contient une erreur
        $responseContent = $this->client->getResponse()->getContent();
        $this->assertIsString($responseContent);
        $this->assertStringContainsString(
            'Un utilisateur ne peut pas être à la fois président et gestionnaire matériel.',
            $responseContent,
            'Form should contain the validation error message'
        );
    }
}
