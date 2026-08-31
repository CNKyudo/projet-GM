<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use App\DataFixtures\AppFixtures;
use App\Entity\Yugake;
use App\Repository\EquipmentRepository;

final class EquipmentBackButtonTest extends AbstractWebTestCase
{
    /** ID du gant appartenant au Club A */
    private int $yugakeAId;

    private int $yugakeBId;

    private bool $isInitialized = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->isInitialized) {
            $this->isInitialized = true;

            $container = self::getContainer();
            /** @var EquipmentRepository $repo */
            $repo = $container->get(EquipmentRepository::class);

            $yugakes = $repo->findAll();

            foreach ($yugakes as $yugake) {
                if (!$yugake instanceof Yugake) {
                    continue;
                }

                if (AppFixtures::CLUB_A === $yugake->getOwnerClub()?->getName()) {
                    $this->yugakeAId = $yugake->getId();
                } elseif (AppFixtures::CLUB_B === $yugake->getOwnerClub()?->getName()) {
                    $this->yugakeBId = $yugake->getId();
                }
            }
        }
    }

    // -----------------------------------------------------------------------
    // Boutons "Retour" / "Annuler" — test du Referer via la session
    // -----------------------------------------------------------------------

    public function testShowPageBackButtonAlwaysLinksToEquipmentList(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->client->request(Request::METHOD_GET, '/equipment/'.$this->yugakeAId);
        $this->assertResponseIsSuccessful();

        $backLink = $crawler->selectLink('Retour à la liste');
        $this->assertSame('/equipment', $backLink->attr('href'));
        $this->assertNull($backLink->attr('data-history-back'), 'The back button should NOT use data-history-back');
    }

    // -----------------------------------------------------------------------
    // Bouton retour garde les arguments de recherche
    // -----------------------------------------------------------------------

    public function testShowPageBackButtonPreservesSearchFilters(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $indexUrlWithFilters = '/equipment?q=test&equipmentType=yugake&status=available';
        $this->client->request(Request::METHOD_GET, $indexUrlWithFilters);
        $this->assertResponseIsSuccessful();

        $crawler = $this->client->request(Request::METHOD_GET, '/equipment/'.$this->yugakeAId);
        $this->assertResponseIsSuccessful();

        $backHref = $crawler->selectLink('Retour à la liste')->attr('href');
        $this->assertSame($indexUrlWithFilters, $backHref, 'Back button should preserve search filters from index');
    }

    public function testShowPageBackButtonPreservesPageNumber(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $indexUrlWithPage = '/equipment?page=2&equipmentType=yugake';
        $this->client->request(Request::METHOD_GET, $indexUrlWithPage);
        $this->assertResponseIsSuccessful();

        $crawler = $this->client->request(Request::METHOD_GET, '/equipment/'.$this->yugakeAId);
        $this->assertResponseIsSuccessful();

        $backHref = $crawler->selectLink('Retour à la liste')->attr('href');
        $this->assertSame($indexUrlWithPage, $backHref);
    }

    public function testCreatePageBackButtonPreservesSearchFilters(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $indexUrlWithFilters = '/equipment?q=test&status=loaned';
        $this->client->request(Request::METHOD_GET, $indexUrlWithFilters);
        $this->assertResponseIsSuccessful();

        $crawler = $this->client->request(Request::METHOD_GET, '/equipment/create');
        $this->assertResponseIsSuccessful();

        $backHref = $crawler->selectLink('Retour à la liste')->attr('href');
        $this->assertSame($indexUrlWithFilters, $backHref, 'Create page back button should preserve search filters');

        $cancelHref = $crawler->selectLink('Annuler')->attr('href');
        $this->assertSame($indexUrlWithFilters, $cancelHref, 'Create page cancel button should preserve search filters');
    }

    public function testEditPageFromListWithFiltersPreservesFilters(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $indexUrlWithFilters = '/equipment?q=test&equipmentType=yugake';
        $this->client->request(Request::METHOD_GET, $indexUrlWithFilters);
        $this->assertResponseIsSuccessful();

        $editUrl = '/equipment/'.$this->yugakeAId.'/edit';
        $crawler = $this->client->request(Request::METHOD_GET, $editUrl, [], [], [
            'HTTP_REFERER' => $indexUrlWithFilters,
        ]);
        $this->assertResponseIsSuccessful();

        $backHref = $crawler->selectLink('Retour')->attr('href');
        $this->assertSame($indexUrlWithFilters, $backHref, 'Edit page back button should preserve index search filters');
    }

    public function testEditRedirectsToIndexWithFiltersAfterSuccess(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $indexUrlWithFilters = '/equipment?q=test&equipmentType=yugake';
        $this->client->request(Request::METHOD_GET, $indexUrlWithFilters);
        $this->assertResponseIsSuccessful();

        $editUrl = '/equipment/'.$this->yugakeAId.'/edit';
        $this->client->request(Request::METHOD_GET, $editUrl, [], [], [
            'HTTP_REFERER' => $indexUrlWithFilters,
        ]);

        $crawler = $this->client->request(Request::METHOD_GET, $editUrl);
        $form = $crawler->selectButton('Enregistrer')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects();
        $this->assertStringContainsString('q=test', (string) $this->client->getResponse()->headers->get('Location'));
        $this->assertStringContainsString('equipmentType=yugake', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testWithoutPriorIndexVisitBackButtonDefaultsToIndex(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $crawler = $this->client->request(Request::METHOD_GET, '/equipment/'.$this->yugakeAId);
        $this->assertResponseIsSuccessful();

        $backHref = $crawler->selectLink('Retour à la liste')->attr('href');
        $this->assertSame('/equipment', $backHref, 'Without prior index visit, back button defaults to /equipment');
    }

    public function testEditPageFromShowPageBackButtonLinksToShowPage(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $editUrl = '/equipment/'.$this->yugakeAId.'/edit';
        $showUrl = '/equipment/'.$this->yugakeAId;

        $crawler = $this->client->request(Request::METHOD_GET, $editUrl, [], [], [
            'HTTP_REFERER' => $showUrl,
        ]);
        $this->assertResponseIsSuccessful();

        $this->assertSame($showUrl, $crawler->selectLink('Retour')->attr('href'));
        $this->assertSame($showUrl, $crawler->selectLink('Annuler')->attr('href'));
    }

    public function testEditPageFromListBackButtonLinksToList(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $editUrl = '/equipment/'.$this->yugakeAId.'/edit';

        $crawler = $this->client->request(Request::METHOD_GET, $editUrl, [], [], [
            'HTTP_REFERER' => '/equipment',
        ]);
        $this->assertResponseIsSuccessful();

        $this->assertSame('/equipment', $crawler->selectLink('Retour')->attr('href'));
        $this->assertSame('/equipment', $crawler->selectLink('Annuler')->attr('href'));
    }

    public function testEditPageUnknownRefererDefaultsToList(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $editUrl = '/equipment/'.$this->yugakeAId.'/edit';

        $crawler = $this->client->request(Request::METHOD_GET, $editUrl, [], [], [
            'HTTP_REFERER' => 'https://evil.com/phishing',
        ]);
        $this->assertResponseIsSuccessful();

        $this->assertSame('/equipment', $crawler->selectLink('Retour')->attr('href'));
    }

    public function testEditPageNoRefererDefaultsToList(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $editUrl = '/equipment/'.$this->yugakeAId.'/edit';

        $crawler = $this->client->request(Request::METHOD_GET, $editUrl);
        $this->assertResponseIsSuccessful();

        $this->assertSame('/equipment', $crawler->selectLink('Retour')->attr('href'));
    }

    public function testEditFormPostWithErrorsPreservesBackHrefFromSession(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $editUrl = '/equipment/'.$this->yugakeAId.'/edit';
        $showUrl = '/equipment/'.$this->yugakeAId;

        $crawler = $this->client->request(Request::METHOD_GET, $editUrl, [], [], [
            'HTTP_REFERER' => $showUrl,
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame($showUrl, $crawler->selectLink('Retour')->attr('href'));

        $crawler = $this->client->request(Request::METHOD_POST, $editUrl, [
            'equipment_form' => [
                'ownerFederation' => '',
                'ownerRegion'     => '',
                'ownerClub'       => '',
                'borrowerClub'    => '',
                'borrowerMember'  => '',
                'state'           => 'used',
                'notes'           => '',
                'yugake_form'      => ['nb_fingers' => '3', 'size' => '7'],
            ],
        ]);

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Vous devez choisir un propriétaire', $content);
        $this->assertSame($showUrl, $crawler->selectLink('Retour')->attr('href'));
        $this->assertSame($showUrl, $crawler->selectLink('Annuler')->attr('href'));
    }

    public function testDifferentEquipmentsHaveIndependentBackHref(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $showA = '/equipment/'.$this->yugakeAId;
        $editA = '/equipment/'.$this->yugakeAId.'/edit';
        $showB = '/equipment/'.$this->yugakeBId;
        $editB = '/equipment/'.$this->yugakeBId.'/edit';

        $crawler = $this->client->request(Request::METHOD_GET, $editA, [], [], [
            'HTTP_REFERER' => $showA,
        ]);
        $this->assertSame($showA, $crawler->selectLink('Retour')->attr('href'));

        $crawler = $this->client->request(Request::METHOD_GET, $editB, [], [], [
            'HTTP_REFERER' => $showB,
        ]);
        $this->assertSame($showB, $crawler->selectLink('Retour')->attr('href'));

        $crawler = $this->client->request(Request::METHOD_POST, $editA, [
            'equipment_form' => [
                'ownerFederation' => '',
                'ownerRegion'     => '',
                'ownerClub'       => '',
                'borrowerClub'    => '',
                'borrowerMember'  => '',
                'state'           => 'used',
                'notes'           => '',
                'yugake_form'      => ['nb_fingers' => '3', 'size' => '7'],
            ],
        ]);
        $this->assertStringContainsString(
            'Vous devez choisir un propriétaire',
            (string) $this->client->getResponse()->getContent()
        );
        $this->assertSame($showA, $crawler->selectLink('Retour')->attr('href'));
    }

    public function testCreatePageBackButtonLinksToList(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->client->request(Request::METHOD_GET, '/equipment/create');
        $this->assertResponseIsSuccessful();

        $this->assertSame('/equipment', $crawler->selectLink('Retour à la liste')->attr('href'));
        $this->assertSame('/equipment', $crawler->selectLink('Annuler')->attr('href'));
    }
}
