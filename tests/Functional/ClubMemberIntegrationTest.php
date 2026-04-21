<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\ClubMember;
use App\Entity\User;
use App\Repository\ClubMemberRepository;
use App\Repository\ClubRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Test d'intégration complet du cycle de vie ClubMember.
 *
 * Scénario :
 *   1. Le président crée un ClubMember non-inscrit dans le Club A.
 *   2. Un utilisateur s'inscrit avec l'email du ClubMember → liaison automatique.
 *   3. Le User est supprimé → user_id = NULL dans club_member (SET NULL).
 *   4. Le ClubMember est supprimé → aucune erreur.
 *   5. On rejoue depuis l'étape 1 → aucune erreur (idempotence).
 */
final class ClubMemberIntegrationTest extends AbstractWebTestCase
{
    private int $clubAId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);

        $clubA = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(\App\Entity\Club::class, $clubA);
        $this->clubAId = $clubA->getId();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        return $em;
    }

    private function clubMemberRepo(): ClubMemberRepository
    {
        /** @var ClubMemberRepository $repo */
        $repo = self::getContainer()->get(ClubMemberRepository::class);

        return $repo;
    }

    private function userRepo(): UserRepository
    {
        /** @var UserRepository $repo */
        $repo = self::getContainer()->get(UserRepository::class);

        return $repo;
    }

    /**
     * Crée un ClubMember non-inscrit via le formulaire (POST /club/{id}/member).
     *
     * @return int L'id du ClubMember créé
     */
    private function createUnregisteredMember(string $email): int
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);

        $this->client->request('POST', '/club/'.$this->clubAId.'/member', [
            'club_member_form' => [
                'firstName'   => 'Nouveau',
                'lastName'    => 'Membre',
                'email'       => $email,
            ],
        ]);

        // Le controller redirige toujours vers club_show (succès ou échec de validation).
        // On vérifie l'URL cible pour écarter une redirection vers /login (authentification échouée).
        $this->assertResponseRedirects('/club/'.$this->clubAId);

        $member = $this->clubMemberRepo()->findOneBy(['email' => $email, 'user' => null]);
        $this->assertInstanceOf(ClubMember::class, $member, 'Le ClubMember doit exister en base après création');
        $this->assertNotInstanceOf(User::class, $member->getUser(), 'Le ClubMember ne doit pas encore être lié à un User');

        return (int) $member->getId();
    }

    /**
     * Inscrit un User via le formulaire /register avec l'email donné.
     */
    private function registerUser(string $email): void
    {
        // On doit être déconnecté pour s'inscrire
        $this->client->request('GET', '/logout');

        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Créer un compte')->form([
            'registration_form[email]'        => $email,
            'registration_form[plainPassword]' => AppFixtures::PASSWORD,
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects(message: 'L\'inscription doit rediriger (succès)');

        // Réinitialiser la session du client : Security::login() régénère l'ID de session
        // et stocke le token du nouvel utilisateur. Si l'on ne vide pas les cookies ici,
        // le prochain loginAs() risque de travailler sur une session périmée ou conflictuelle.
        $this->client->getCookieJar()->clear();
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    /**
     * Étape 1 : le président peut créer un ClubMember non-inscrit.
     */
    public function testPresidentCanCreateUnregisteredMember(): void
    {
        $email = 'integration.test.create@kyudo-test.fr';

        $memberId = $this->createUnregisteredMember($email);

        $this->assertGreaterThan(0, $memberId);
    }

    /**
     * Étape 2 : inscription avec le même email → liaison automatique User↔ClubMember.
     */
    public function testRegistrationLinksClubMember(): void
    {
        $email = 'integration.test.link@kyudo-test.fr';

        // Créer le ClubMember non-inscrit
        $memberId = $this->createUnregisteredMember($email);

        // Inscrire un User avec le même email
        $this->registerUser($email);

        // Vérifier la liaison en base
        $em = $this->em();
        $em->clear();

        $member = $this->clubMemberRepo()->find($memberId);
        $this->assertInstanceOf(ClubMember::class, $member);
        $this->assertInstanceOf(User::class, $member->getUser(), 'Le ClubMember doit être lié au User après inscription');
        $this->assertSame($email, $member->getUser()->getEmail());
    }

    /**
     * Étape 3 : supprimer le User → user_id = NULL dans club_member (ON DELETE SET NULL).
     */
    public function testDeleteUserSetsClubMemberUserToNull(): void
    {
        $email = 'integration.test.delete-user@kyudo-test.fr';

        $memberId = $this->createUnregisteredMember($email);
        $this->registerUser($email);

        $em = $this->em();
        $em->clear();

        $user = $this->userRepo()->findOneBy(['email' => $email]);
        $this->assertInstanceOf(User::class, $user);

        $em->remove($user);
        $em->flush();
        $em->clear();

        $member = $this->clubMemberRepo()->find($memberId);
        $this->assertInstanceOf(ClubMember::class, $member, 'Le ClubMember doit encore exister après suppression du User');
        $this->assertNotInstanceOf(User::class, $member->getUser(), 'user_id doit être NULL après suppression du User (ON DELETE SET NULL)');
    }

    /**
     * Étape 4 : supprimer le ClubMember → aucune erreur.
     */
    public function testDeleteClubMemberSucceeds(): void
    {
        $email = 'integration.test.delete-member@kyudo-test.fr';

        $memberId = $this->createUnregisteredMember($email);

        $em = $this->em();
        $em->clear();

        $member = $this->clubMemberRepo()->find($memberId);
        $this->assertInstanceOf(ClubMember::class, $member);

        $em->remove($member);
        $em->flush();
        $em->clear();

        $this->assertNotInstanceOf(ClubMember::class, $this->clubMemberRepo()->find($memberId), 'Le ClubMember doit être supprimé');
    }

    /**
     * Étape 5 (idempotence) : scénario complet rejoué — création → inscription → suppression User → suppression ClubMember.
     */
    public function testFullCycleIsIdempotent(): void
    {
        $email = 'integration.test.cycle@kyudo-test.fr';

        foreach (range(1, 2) as $run) {
            // Créer le membre non-inscrit
            $memberId = $this->createUnregisteredMember($email);

            // Inscrire le User
            $this->registerUser($email);

            $em = $this->em();
            $em->clear();

            // Vérifier liaison
            $member = $this->clubMemberRepo()->find($memberId);
            $this->assertInstanceOf(ClubMember::class, $member, sprintf('Run %s : membre introuvable', $run));
            $this->assertInstanceOf(User::class, $member->getUser(), sprintf('Run %s : liaison User manquante', $run));

            // Supprimer le User
            $user = $this->userRepo()->findOneBy(['email' => $email]);
            $this->assertInstanceOf(User::class, $user);
            $em->remove($user);
            $em->flush();
            $em->clear();

            // Vérifier SET NULL
            $member = $this->clubMemberRepo()->find($memberId);
            $this->assertInstanceOf(ClubMember::class, $member);
            $this->assertNotInstanceOf(User::class, $member->getUser(), sprintf('Run %s : user_id devrait être NULL', $run));

            // Supprimer le ClubMember
            $em->remove($member);
            $em->flush();
            $em->clear();

            $this->assertNotInstanceOf(ClubMember::class, $this->clubMemberRepo()->find($memberId), sprintf('Run %s : ClubMember devrait être supprimé', $run));
        }
    }
}
