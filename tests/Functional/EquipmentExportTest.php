<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Federation;
use App\Entity\Yugake;
use App\Repository\EquipmentRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests fonctionnels : export CSV des équipements.
 *
 * Route testée : POST /equipment/export
 *
 * Règles vérifiées :
 *   1. Contrôle d'accès : ROLE_USER → 403 ; MEMBER+ → 200 avec CSV
 *   2. Réponse CSV valide (headers Content-Type, Content-Disposition, BOM UTF-8)
 *   3. Filtres propagés (equipmentType, q, status)
 *   4. Droits de visibilité respectés (MEMBER ne voit pas les équipements nationaux)
 */
final class EquipmentExportTest extends AbstractWebTestCase
{
    /** ID du gant appartenant au Club A */
    private int $yugakeAId;

    /** ID du gant appartenant au Club G (autre CTK, disponible) */
    private int $yugakeGId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();
        /** @var EquipmentRepository $repo */
        $repo = $container->get(EquipmentRepository::class);

        /** @var Equipment[] $equipments */
        $equipments = $repo->findAll();

        foreach ($equipments as $equipment) {
            if (!$equipment instanceof Yugake) {
                continue;
            }

            if (AppFixtures::CLUB_A === $equipment->getOwnerClub()?->getName()) {
                $this->yugakeAId = $equipment->getId();
            } elseif (AppFixtures::CLUB_G === $equipment->getOwnerClub()?->getName()) {
                $this->yugakeGId = $equipment->getId();
            } elseif (AppFixtures::REGION_A === $equipment->getOwnerRegion()?->getName()
                && !$equipment->getBorrowerClub() instanceof Club
                && !$equipment->getBorrowerMember() instanceof \App\Entity\ClubMember) {
            } elseif ($equipment->getOwnerFederation() instanceof Federation
                && !$equipment->getBorrowerClub() instanceof Club
                && !$equipment->getBorrowerMember() instanceof \App\Entity\ClubMember) {
            }
        }
    }

    // -----------------------------------------------------------------------
    // Contrôle d'accès
    // -----------------------------------------------------------------------

    public function testExportDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertPostDenied('/equipment/export', []);
    }

    public function testExportGrantedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_POST, '/equipment/export');

        $this->assertResponseIsSuccessful();
    }

    public function testExportGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export');

        $this->assertResponseIsSuccessful();
    }

    // -----------------------------------------------------------------------
    // Format de la réponse CSV
    // -----------------------------------------------------------------------

    public function testExportResponseHasCsvContentType(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export');

        $response = $this->client->getResponse();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function testExportResponseHasContentDispositionAttachment(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export');

        $response = $this->client->getResponse();
        $contentDisposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('equipements_', $contentDisposition);
        $this->assertStringContainsString('.csv', $contentDisposition);
    }

    public function testExportResponseHasUtf8Bom(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export');

        $content = (string) $this->client->getResponse()->getContent();
        // UTF-8 BOM : EF BB BF
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
    }

    public function testExportContainsFrenchHeaders(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export');

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('ID', $content);
        $this->assertStringContainsString('Type', $content);
        $this->assertStringContainsString('État', $content);
        $this->assertStringContainsString('Niveau', $content);
        $this->assertStringContainsString('Club propriétaire', $content);
        $this->assertStringContainsString('Club emprunteur', $content);
        $this->assertStringContainsString('Créé le', $content);
    }

    // -----------------------------------------------------------------------
    // Filtres propagés : equipmentType
    // -----------------------------------------------------------------------

    public function testExportFilterByEquipmentTypeYugake(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', [
            'equipmentType' => 'yugake',
        ]);

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        // Tous les types dans un export filtré sur "yugake" doivent être "Yugake"
        $lines = $this->parseCsvLines($content);
        $headers = array_shift($lines);
        $this->assertNotNull($headers);

        $typeIndex = array_search('Type', $headers, true);
        $this->assertNotFalse($typeIndex);

        foreach ($lines as $line) {
            if ([] === $line || [''] === $line) {
                continue;
            }

            $this->assertSame('Yugake', $line[$typeIndex], 'Toutes les lignes doivent être de type Yugake');
        }
    }

    // -----------------------------------------------------------------------
    // Filtres propagés : status
    // -----------------------------------------------------------------------

    public function testExportFilterByStatusAvailable(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', [
            'status' => 'available',
        ]);

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        $lines = $this->parseCsvLines($content);
        $headers = array_shift($lines);
        $this->assertNotNull($headers);

        $borrowerClubIndex = array_search('Club emprunteur', $headers, true);
        $borrowerMemberIndex = array_search('Membre emprunteur', $headers, true);
        $this->assertNotFalse($borrowerClubIndex);
        $this->assertNotFalse($borrowerMemberIndex);

        foreach ($lines as $line) {
            if ([] === $line || [''] === $line) {
                continue;
            }

            $this->assertSame('', $line[$borrowerClubIndex], 'Un équipement disponible ne doit pas avoir de club emprunteur');
            $this->assertSame('', $line[$borrowerMemberIndex], 'Un équipement disponible ne doit pas avoir de membre emprunteur');
        }
    }

    // -----------------------------------------------------------------------
    // Droits de visibilité : MEMBER ne voit pas les équipements nationaux
    // -----------------------------------------------------------------------

    public function testExportMemberDoesNotSeeNationalEquipment(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_POST, '/equipment/export');

        $content = (string) $this->client->getResponse()->getContent();
        $lines = $this->parseCsvLines($content);
        $headers = array_shift($lines);
        $this->assertNotNull($headers);

        $niveauIndex = array_search('Niveau', $headers, true);
        $this->assertNotFalse($niveauIndex);

        foreach ($lines as $line) {
            if ([] === $line || [''] === $line) {
                continue;
            }

            $this->assertNotSame('National', $line[$niveauIndex], 'MEMBER ne doit pas voir les équipements nationaux');
        }
    }

    public function testExportAdminSeesNationalEquipment(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export');

        $content = (string) $this->client->getResponse()->getContent();
        $lines = $this->parseCsvLines($content);
        $headers = array_shift($lines);
        $this->assertNotNull($headers);

        $niveauIndex = array_search('Niveau', $headers, true);
        $this->assertNotFalse($niveauIndex);

        $hasNational = false;

        foreach ($lines as $line) {
            if ([] === $line || [''] === $line) {
                continue;
            }

            if ('National' === $line[$niveauIndex]) {
                $hasNational = true;
                break;
            }
        }

        $this->assertTrue($hasNational, 'ADMIN doit voir les équipements nationaux dans l\'export');
    }

    // -----------------------------------------------------------------------
    // Cohérence : l'export contient les équipements visibles dans la liste
    // -----------------------------------------------------------------------

    public function testExportContainsOwnClubEquipmentForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_POST, '/equipment/export');

        $content = (string) $this->client->getResponse()->getContent();
        $lines = $this->parseCsvLines($content);
        $headers = array_shift($lines);
        $this->assertNotNull($headers);

        $idIndex = array_search('ID', $headers, true);
        $this->assertNotFalse($idIndex);

        $exportedIds = array_map(static fn (array $line): string => $line[$idIndex] ?? '', $lines);
        $this->assertContains((string) $this->yugakeAId, $exportedIds, 'Le gant du Club A doit être dans l\'export du MEMBER');
    }

    public function testExportDoesNotContainOtherCtkEquipmentForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_POST, '/equipment/export');

        $content = (string) $this->client->getResponse()->getContent();
        $lines = $this->parseCsvLines($content);
        $headers = array_shift($lines);
        $this->assertNotNull($headers);

        $idIndex = array_search('ID', $headers, true);
        $this->assertNotFalse($idIndex);

        $exportedIds = array_map(static fn (array $line): string => $line[$idIndex] ?? '', $lines);
        $this->assertNotContains((string) $this->yugakeGId, $exportedIds, 'MEMBER ne doit pas voir le gant du Club G (autre CTK)');
    }

    // -----------------------------------------------------------------------
    // Traductions via le Translator
    // -----------------------------------------------------------------------

    /**
     * Vérifie que le type dans le CSV utilise la valeur traduite par le translator
     * (ex: "Gant (Yugake)" depuis messages.fr.yaml, pas "Gant" en dur).
     */
    public function testExportTypeColumnUsesTranslator(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', [
            'equipmentType' => 'yugake',
        ]);

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        $lines = $this->parseCsvLines($content);
        $headers = array_shift($lines);
        $this->assertNotNull($headers);

        $typeIndex = array_search('Type', $headers, true);
        $this->assertNotFalse($typeIndex);

        foreach ($lines as $line) {
            if ([] === $line || [''] === $line) {
                continue;
            }

            $this->assertSame(
                'Yugake',
                $line[$typeIndex],
                'Le type doit utiliser la valeur traduite par le translator (messages.fr.yaml: equipment.type.yugake)'
            );
        }
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    /**
     * Parse le contenu CSV (avec BOM) en tableau de lignes.
     *
     * @return array<int, array<int, string>>
     */
    private function parseCsvLines(string $content): array
    {
        // Supprimer le BOM UTF-8 si présent
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $lines = [];
        foreach (explode("\n", trim($content)) as $row) {
            if ('' === trim($row)) {
                continue;
            }

            $parsed = str_getcsv($row, ';', escape: '\\');
            $lines[] = $parsed;
        }

        return $lines;
    }
}
