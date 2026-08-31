<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Equipment;
use App\Entity\Yugake;
use App\Repository\EquipmentRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests fonctionnels : export Excel (XLSX) des équipements.
 *
 * Route testée : POST /equipment/export?format=xlsx
 *
 * Règles vérifiées :
 *   1. Contrôle d'accès : ROLE_USER → 403 ; MEMBER+ → 200 avec XLSX
 *   2. Réponse Excel valide (headers Content-Type, Content-Disposition)
 *   3. Contenu XLSX valide (magic number PK, parseable par PhpSpreadsheet)
 *   4. Filtres propagés (equipmentType, q, status)
 *   5. Droits de visibilité respectés (MEMBER ne voit pas les équipements nationaux)
 *   6. En-têtes identiques au CSV
 */
final class EquipmentExportExcelTest extends AbstractWebTestCase
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
            }
        }
    }

    // -----------------------------------------------------------------------
    // Contrôle d'accès
    // -----------------------------------------------------------------------

    public function testExportExcelDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertPostDenied('/equipment/export', ['format' => 'xlsx']);
    }

    public function testExportExcelGrantedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_POST, '/equipment/export', ['format' => 'xlsx']);

        $this->assertResponseIsSuccessful();
    }

    public function testExportExcelGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', ['format' => 'xlsx']);

        $this->assertResponseIsSuccessful();
    }

    // -----------------------------------------------------------------------
    // Format de la réponse Excel
    // -----------------------------------------------------------------------

    public function testExportExcelResponseHasXlsxContentType(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', ['format' => 'xlsx']);

        $response = $this->client->getResponse();
        $contentType = (string) $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $contentType);
    }

    public function testExportExcelResponseHasContentDispositionAttachment(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', ['format' => 'xlsx']);

        $response = $this->client->getResponse();
        $contentDisposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('equipements_', $contentDisposition);
        $this->assertStringContainsString('.xlsx', $contentDisposition);
    }

    public function testExportExcelResponseHasValidXlsxMagicNumber(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', ['format' => 'xlsx']);

        $content = (string) $this->client->getResponse()->getContent();
        // Les fichiers XLSX (ZIP) commencent par PK (magic number)
        $this->assertStringStartsWith('PK', $content);
    }

    public function testExportExcelResponseIsParseableByPhpSpreadsheet(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', ['format' => 'xlsx']);

        $content = (string) $this->client->getResponse()->getContent();

        try {
            $spreadsheet = $this->loadSpreadsheetFromContent($content);
            $sheet = $spreadsheet->getActiveSheet();
            // Vérifier qu'il y a des données
            $this->assertGreaterThan(0, $sheet->getHighestRow());
        } catch (ReaderException $readerException) {
            $this->fail(sprintf("Le contenu XLSX n'est pas valide : %s", $readerException->getMessage()));
        }
    }

    public function testExportExcelContainsFrenchHeaders(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', ['format' => 'xlsx']);

        $content = (string) $this->client->getResponse()->getContent();
        $spreadsheet = $this->loadSpreadsheetFromContent($content);
        $sheet = $spreadsheet->getActiveSheet();

        $data = $sheet->toArray();
        $headers = $data[0] ?? [];

        $expectedHeaders = [
            'ID',
            'Type',
            'État',
            'Niveau',
            'Club propriétaire',
            'Région dépositaire',
            'Fédération propriétaire',
            'Club emprunteur',
            'Membre emprunteur',
        ];

        foreach ($expectedHeaders as $expectedHeader) {
            $this->assertContains($expectedHeader, $headers, sprintf('L\'en-tête "%s" doit être présent', $expectedHeader));
        }
    }

    // -----------------------------------------------------------------------
    // Filtres propagés : equipmentType
    // -----------------------------------------------------------------------

    public function testExportExcelFilterByEquipmentTypeYugake(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', [
            'format' => 'xlsx',
            'equipmentType' => 'yugake',
        ]);

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $spreadsheet = $this->loadSpreadsheetFromContent($content);
        $sheet = $spreadsheet->getActiveSheet();

        $data = $sheet->toArray();
        $headers = $data[0] ?? [];
        $typeIndex = array_search('Type', $headers, true);
        $this->assertNotFalse($typeIndex);
        $counter = count($data);

        // Vérifier que toutes les lignes (sauf header) sont de type "Yugake"
        for ($i = 1; $i < $counter; ++$i) {
            $row = $data[$i];
            if (!isset($row[$typeIndex]) || '' === $row[$typeIndex]) {
                continue;
            }

            $this->assertSame('Yugake', (string) $row[$typeIndex], 'Toutes les lignes doivent être de type Yugake');
        }
    }

    // -----------------------------------------------------------------------
    // Filtres propagés : status
    // -----------------------------------------------------------------------

    public function testExportExcelFilterByStatusAvailable(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', [
            'format' => 'xlsx',
            'status' => 'available',
        ]);

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $spreadsheet = $this->loadSpreadsheetFromContent($content);
        $sheet = $spreadsheet->getActiveSheet();

        $data = $sheet->toArray();
        $headers = $data[0] ?? [];
        $borrowerClubIndex = array_search('Club emprunteur', $headers, true);
        $borrowerMemberIndex = array_search('Membre emprunteur', $headers, true);
        $this->assertNotFalse($borrowerClubIndex);
        $this->assertNotFalse($borrowerMemberIndex);
        $counter = count($data);

        for ($i = 1; $i < $counter; ++$i) {
            $row = $data[$i];
            $borrowerClub = (string) ($row[$borrowerClubIndex] ?? '');
            $borrowerMember = (string) ($row[$borrowerMemberIndex] ?? '');

            $this->assertSame('', $borrowerClub, 'Un équipement disponible ne doit pas avoir de club emprunteur');
            $this->assertSame('', $borrowerMember, 'Un équipement disponible ne doit pas avoir de membre emprunteur');
        }
    }

    // -----------------------------------------------------------------------
    // Droits de visibilité : MEMBER ne voit pas les équipements nationaux
    // -----------------------------------------------------------------------

    public function testExportExcelMemberDoesNotSeeNationalEquipment(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_POST, '/equipment/export', ['format' => 'xlsx']);

        $content = (string) $this->client->getResponse()->getContent();
        $spreadsheet = $this->loadSpreadsheetFromContent($content);
        $sheet = $spreadsheet->getActiveSheet();

        $data = $sheet->toArray();
        $headers = $data[0] ?? [];
        $niveauIndex = array_search('Niveau', $headers, true);
        $this->assertNotFalse($niveauIndex);
        $counter = count($data);

        for ($i = 1; $i < $counter; ++$i) {
            $row = $data[$i];
            $niveau = (string) ($row[$niveauIndex] ?? '');
            $this->assertNotSame('National', $niveau, 'MEMBER ne doit pas voir les équipements nationaux');
        }
    }

    public function testExportExcelAdminSeesNationalEquipment(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', ['format' => 'xlsx']);

        $content = (string) $this->client->getResponse()->getContent();
        $spreadsheet = $this->loadSpreadsheetFromContent($content);
        $sheet = $spreadsheet->getActiveSheet();

        $data = $sheet->toArray();
        $headers = $data[0] ?? [];
        $niveauIndex = array_search('Niveau', $headers, true);
        $this->assertNotFalse($niveauIndex);

        $hasNational = false;
        $counter = count($data);
        for ($i = 1; $i < $counter; ++$i) {
            $row = $data[$i];
            $niveau = (string) ($row[$niveauIndex] ?? '');
            if ('National' === $niveau) {
                $hasNational = true;
                break;
            }
        }

        $this->assertTrue($hasNational, 'ADMIN doit voir les équipements nationaux dans l\'export');
    }

    // -----------------------------------------------------------------------
    // Cohérence : l'export contient les équipements visibles dans la liste
    // -----------------------------------------------------------------------

    public function testExportExcelContainsOwnClubEquipmentForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_POST, '/equipment/export', ['format' => 'xlsx']);

        $content = (string) $this->client->getResponse()->getContent();
        $spreadsheet = $this->loadSpreadsheetFromContent($content);
        $sheet = $spreadsheet->getActiveSheet();

        $data = $sheet->toArray();
        $headers = $data[0] ?? [];
        $idIndex = array_search('ID', $headers, true);
        $this->assertNotFalse($idIndex);

        $exportedIds = array_column(array_slice($data, 1), $idIndex);
        $this->assertContains((string) $this->yugakeAId, $exportedIds, 'Le gant du Club A doit être dans l\'export du MEMBER');
    }

    public function testExportExcelDoesNotContainOtherCtkEquipmentForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_POST, '/equipment/export', ['format' => 'xlsx']);

        $content = (string) $this->client->getResponse()->getContent();
        $spreadsheet = $this->loadSpreadsheetFromContent($content);
        $sheet = $spreadsheet->getActiveSheet();

        $data = $sheet->toArray();
        $headers = $data[0] ?? [];
        $idIndex = array_search('ID', $headers, true);
        $this->assertNotFalse($idIndex);

        $exportedIds = array_column(array_slice($data, 1), $idIndex);
        $this->assertNotContains((string) $this->yugakeGId, $exportedIds, 'MEMBER ne doit pas voir le gant du Club G (autre CTK)');
    }

    // -----------------------------------------------------------------------
    // Traductions via le Translator
    // -----------------------------------------------------------------------

    public function testExportExcelTypeColumnUsesTranslator(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/equipment/export', [
            'format' => 'xlsx',
            'equipmentType' => 'yugake',
        ]);

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $spreadsheet = $this->loadSpreadsheetFromContent($content);
        $sheet = $spreadsheet->getActiveSheet();

        $data = $sheet->toArray();
        $headers = $data[0] ?? [];
        $typeIndex = array_search('Type', $headers, true);
        $this->assertNotFalse($typeIndex);
        $counter = count($data);

        for ($i = 1; $i < $counter; ++$i) {
            $row = $data[$i];
            $type = (string) ($row[$typeIndex] ?? '');
            $this->assertSame(
                'Gant (Yugake)',
                $type,
                'Le type doit utiliser la valeur traduite par le translator (messages.fr.yaml: equipment.type.yugake)'
            );
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Charge un Spreadsheet à partir du contenu XLSX.
     */
    private function loadSpreadsheetFromContent(string $content): Spreadsheet
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        file_put_contents($tempFile, $content);

        try {
            return IOFactory::load($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
