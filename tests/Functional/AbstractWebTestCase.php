<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use App\DataFixtures\AppFixtures;
use App\Repository\UserRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Classe de base pour les tests fonctionnels.
 *
 * Fournit :
 *   - le rechargement des fixtures avant chaque test
 *   - un helper loginAs(email) pour simuler une session authentifiée
 *   - un helper assertAccessGranted / assertAccessDenied
 */
abstract class AbstractWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loadFixtures();
    }

    // -----------------------------------------------------------------------
    // Fixtures
    // -----------------------------------------------------------------------

    private function loadFixtures(): void
    {
        $container = static::getContainer();

        /** @var ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var EntityManagerInterface $objectManager */
        $objectManager = $doctrine->getManager();

        $loader = new Loader();
        $fixture = $container->get(AppFixtures::class);
        $this->assertInstanceOf(AppFixtures::class, $fixture);
        $loader->addFixture($fixture);

        // Sur PostgreSQL, TRUNCATE CASCADE est bloqué par les FK inter-tables.
        // On désactive temporairement les FK via session_replication_role,
        // puis on recharge avec TRUNCATE pour réinitialiser les séquences.
        $connection = $objectManager->getConnection();
        $connection->executeStatement('SET session_replication_role = replica');

        $ormPurger = new ORMPurger($objectManager);
        $ormPurger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);

        $ormExecutor = new ORMExecutor($objectManager, $ormPurger);
        $ormExecutor->execute($loader->getFixtures());

        $connection->executeStatement('SET session_replication_role = DEFAULT');
    }

    // -----------------------------------------------------------------------
    // Authentication
    // -----------------------------------------------------------------------

    /**
     * Connecte un utilisateur via son email (créé par AppFixtures).
     */
    protected function loginAs(string $email): void
    {
        $container = static::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);

        $user = $userRepo->findOneBy(['email' => $email]);
        if (null === $user) {
            throw new \RuntimeException(sprintf('Fixture user "%s" not found.', $email));
        }

        $this->client->loginUser($user);
    }

    // -----------------------------------------------------------------------
    // Assertion helpers
    // -----------------------------------------------------------------------

    /**
     * Asserts that a GET request returns 200 (access granted).
     */
    protected function assertGetGranted(string $url, string $message = ''): void
    {
        $this->client->request(Request::METHOD_GET, $url);
        $statusCode = $this->client->getResponse()->getStatusCode();

        $this->assertContains(
            $statusCode,
            [200, 302], // 302 = redirect after success (e.g. after form submit)
            ($message ?: sprintf('GET %s should be accessible', $url))
            .sprintf(' (got %d)', $statusCode)
        );
    }

    /**
     * Asserts that a GET request returns 403 (access denied).
     */
    protected function assertGetDenied(string $url, string $message = ''): void
    {
        $this->client->request(Request::METHOD_GET, $url);
        $statusCode = $this->client->getResponse()->getStatusCode();

        $this->assertSame(
            403,
            $statusCode,
            ($message ?: sprintf('GET %s should be forbidden', $url))
            .sprintf(' (got %d)', $statusCode)
        );
    }

    /**
     * Submits a POST form and asserts that the response redirects (302) — i.e. success.
     *
     * @param array<string, mixed> $formData
     */
    protected function assertPostRedirects(string $url, array $formData, string $message = ''): void
    {
        $this->client->request(Request::METHOD_POST, $url, $formData);
        $statusCode = $this->client->getResponse()->getStatusCode();

        $this->assertSame(
            302,
            $statusCode,
            ($message ?: sprintf('POST %s should redirect on success', $url))
            .sprintf(' (got %d)', $statusCode)
        );
    }

    /**
     * Submits a POST form and asserts that the response is 403 (access denied).
     *
     * @param array<string, mixed> $formData
     */
    protected function assertPostDenied(string $url, array $formData, string $message = ''): void
    {
        $this->client->request(Request::METHOD_POST, $url, $formData);
        $statusCode = $this->client->getResponse()->getStatusCode();

        $this->assertSame(
            403,
            $statusCode,
            ($message ?: sprintf('POST %s should be forbidden', $url))
            .sprintf(' (got %d)', $statusCode)
        );
    }

    /**
     * Fetches the form at $url, fills in the given fields by label/name, submits it,
     * and asserts a redirect (302).
     *
     * @param array<string, mixed> $fields Associative array of field names → values
     */
    protected function assertFormSubmitRedirects(string $url, string $submitButtonText, array $fields, string $message = ''): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, $url);
        $form = $crawler->selectButton($submitButtonText)->form();

        foreach ($fields as $name => $value) {
            $form[$name] = $value;
        }

        $this->client->submit($form);
        $statusCode = $this->client->getResponse()->getStatusCode();

        $this->assertSame(
            302,
            $statusCode,
            ($message ?: sprintf('Form submit at %s should redirect on success', $url))
            .sprintf(' (got %d)', $statusCode)
        );
    }

    /**
     * Fetches the form at $url, fills in the given fields, submits it,
     * and asserts a 403 (access denied) on submit.
     *
     * @param array<string, mixed> $fields
     */
    protected function assertFormSubmitDenied(string $url, string $submitButtonText, array $fields, string $message = ''): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, $url);
        $form = $crawler->selectButton($submitButtonText)->form();

        foreach ($fields as $name => $value) {
            $form[$name] = $value;
        }

        $this->client->submit($form);
        $statusCode = $this->client->getResponse()->getStatusCode();

        $this->assertSame(
            403,
            $statusCode,
            ($message ?: sprintf('Form submit at %s should be forbidden', $url))
            .sprintf(' (got %d)', $statusCode)
        );
    }
}
