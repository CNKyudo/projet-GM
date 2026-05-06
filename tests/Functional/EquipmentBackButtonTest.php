<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use App\DataFixtures\AppFixtures;
use App\Entity\Glove;
use App\Repository\EquipmentRepository;

final class EquipmentBackButtonTest extends AbstractWebTestCase
{
    /** ID du gant appartenant au Club A */
    private int $gloveAId;

    /** ID du gant appartenant au Club B — emprunté par Club C */
    private int $gloveBId;

    private bool $isInitialized = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->isInitialized) {
            $this->isInitialized = true;

            $container = self::getContainer();
            /** @var EquipmentRepository $repo */
            $repo = $container->get(EquipmentRepository::class);

            $gloves = $repo->findAll();

            foreach ($gloves as $glove) {
                if (!$glove instanceof Glove) {
                    continue;
                }

                if (AppFixtures::CLUB_A === $glove->getOwnerClub()?->getName()) {
                    $this->gloveAId = $glove->getId();
                } elseif (AppFixtures::CLUB_B === $glove->getOwnerClub()?->getName()) {
                    $this->gloveBId = $glove->getId();
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
        $crawler = $this->client->request(Request::METHOD_GET, '/equipment/'.$this->gloveAId);
        $this->assertResponseIsSuccessful();

        $backLink = $crawler->selectLink('Retour à la liste');
        $this->assertSame('/equipment', $backLink->attr('href'));
        $this->assertNull($backLink->attr('data-history-back'), 'The back button should NOT use data-history-back');
    }

    public function testEditPageFromShowPageBackButtonLinksToShowPage(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $editUrl = '/equipment/'.$this->gloveAId.'/edit';
        $showUrl = '/equipment/'.$this->gloveAId;

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
        $editUrl = '/equipment/'.$this->gloveAId.'/edit';

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
        $editUrl = '/equipment/'.$this->gloveAId.'/edit';

        $crawler = $this->client->request(Request::METHOD_GET, $editUrl, [], [], [
            'HTTP_REFERER' => 'https://evil.com/phishing',
        ]);
        $this->assertResponseIsSuccessful();

        $this->assertSame('/equipment', $crawler->selectLink('Retour')->attr('href'));
    }

    public function testEditPageNoRefererDefaultsToList(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $editUrl = '/equipment/'.$this->gloveAId.'/edit';

        $crawler = $this->client->request(Request::METHOD_GET, $editUrl);
        $this->assertResponseIsSuccessful();

        $this->assertSame('/equipment', $crawler->selectLink('Retour')->attr('href'));
    }

    public function testEditFormPostWithErrorsPreservesBackHrefFromSession(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $editUrl = '/equipment/'.$this->gloveAId.'/edit';
        $showUrl = '/equipment/'.$this->gloveAId;

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
                'glove_form'      => ['nb_fingers' => '3', 'size' => '7'],
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

        $showA = '/equipment/'.$this->gloveAId;
        $editA = '/equipment/'.$this->gloveAId.'/edit';
        $showB = '/equipment/'.$this->gloveBId;
        $editB = '/equipment/'.$this->gloveBId.'/edit';

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
                'glove_form'      => ['nb_fingers' => '3', 'size' => '7'],
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
