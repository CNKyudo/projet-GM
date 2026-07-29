<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Equipment;
use App\Entity\Etafoam;
use App\Entity\Gake;
use App\Entity\Makiwara;
use App\Entity\Maku;
use App\Entity\Muneate;
use App\Entity\Shitagake;
use App\Entity\SupportMakiwara;
use App\Entity\Tsuru;
use App\Entity\Yatate;
use App\Entity\Yumi;
use App\Entity\Yumitate;
use App\Enum\EquipmentLevel;
use App\Enum\EquipmentState;
use App\Enum\EquipmentType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class EquipmentExportService
{
    private const string SEPARATOR = ';';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Construit une réponse HTTP contenant le CSV des équipements fournis.
     *
     * @param Equipment[] $equipments
     */
    public function buildCsvResponse(array $equipments): Response
    {
        $filename = 'equipements_'.date('Ymd_His').'.csv';

        $rows = [];
        $rows[] = $this->buildHeaders();

        foreach ($equipments as $equipment) {
            $rows[] = $this->buildRow($equipment);
        }

        $csv = implode("\n", array_map($this->encodeRow(...), $rows));

        // UTF-8 BOM pour compatibilité Excel
        $content = "\xEF\xBB\xBF".$csv;

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    /**
     * @param array<int|string, Equipment> $equipments
     */
    public function buildExcelResponse(array $equipments): Response
    {
        $filename = 'equipements_'.date('Ymd_His').'.xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($this->buildHeaders(), null, 'A1');

        $row = 2;

        foreach ($equipments as $equipment) {
            $sheet->fromArray(
                $this->buildRow($equipment),
                null,
                'A'.$row
            );

            ++$row;
        }

        $headerCount = count($this->buildHeaders());
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerCount);

        $sheet->getStyle(sprintf('A1:%s1', $lastColumn))
            ->getFont()
            ->setBold(true);

        // Autosize columns - use numeric index instead of range() to avoid PHP warning with multi-byte column letters
        for ($i = 1; $i <= $headerCount; ++$i) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = (string) ob_get_clean();

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    /**
     * @return string[]
     */
    private function buildHeaders(): array
    {
        return [
            'ID',
            'Type',
            'État',
            'Niveau',
            'Club propriétaire',
            'Région propriétaire',
            'Fédération propriétaire',
            'Club emprunteur',
            'Membre emprunteur',
            // Colonnes spécifiques par type
            'Matériau',           // Makiwara, Maku, Muneate, Shitagake, Yumi
            'Force (kg)',         // Yumi
            'Longueur arc',       // Yumi
            'Taille (tsuru)',     // Tsuru
            'Nb doigts',          // Gake, Shitagake
            'Taille',             // Gake, Muneate, Shitagake
            'Hauteur (cm)',       // SupportMakiwara, Maku
            'Nb arcs',            // Yumitate
            'Orientation',        // Yumitate
            'Nb flèches',         // Yatate
            'Longueur (cm)',      // Maku, Etafoam
            'Largeur (cm)',       // Etafoam
            'Épaisseur (cm)',     // Etafoam
            'Quantité',           // Etafoam, Muneate, Shitagake, Tsuru
            'Poids (kg)',         // Maku
            'Attache',            // Maku
            'Force min (kg)',     // Tsuru
            'Force max (kg)',     // Tsuru
            'Notes',
            'Créé le',
            'Modifié le',
        ];
    }

    /**
     * @return string[]
     */
    private function buildRow(Equipment $equipment): array
    {
        return [
            (string) ($equipment->getId() ?? ''),
            $this->translateType($equipment::getType()),
            $this->translateState($equipment->getState()),
            $this->translateLevel($equipment->getEquipmentLevel()),
            $equipment->getOwnerClub()?->getName() ?? '',
            $equipment->getOwnerRegion()?->getName() ?? '',
            $equipment->getOwnerFederation()?->getName() ?? '',
            $equipment->getBorrowerClub()?->getName() ?? '',
            $equipment->getBorrowerMember()?->getFullName() ?? '',
            // Colonnes spécifiques
            $this->extractMaterial($equipment),
            $this->extractYumiStrength($equipment),
            $this->extractYumiLength($equipment),
            $this->extractNbFingers($equipment),
            $this->extractSize($equipment),
            $this->extractHeight($equipment),
            $this->extractNbBows($equipment),
            $this->extractYumitateOrientation($equipment),
            $this->extractNbArrows($equipment),
            $this->extractLength($equipment),
            $this->extractEtafoamWidth($equipment),
            $this->extractEtafoamThickness($equipment),
            $this->extractQuantity($equipment),
            $this->extractMakuWeight($equipment),
            $this->extractTsuruStrengthMin($equipment),
            $this->extractTsuruStrengthMax($equipment),
            $this->extractTsuruLength($equipment),
            $this->extractMakuAttachment($equipment),
            $equipment->getNotes() ?? '',
            $equipment->getCreatedAt()?->format('d/m/Y H:i') ?? '',
            $equipment->getUpdatedAt()?->format('d/m/Y H:i') ?? '',
        ];
    }

    private function translateType(EquipmentType $type): string
    {
        return $this->translator->trans($type->label());
    }

    private function translateState(EquipmentState $state): string
    {
        return $this->translator->trans($state->label());
    }

    private function translateLevel(EquipmentLevel $level): string
    {
        return $this->translator->trans($level->label());
    }

    private function extractMaterial(Equipment $equipment): string
    {
        if ($equipment instanceof Makiwara) {
            return $this->extractEnumValue($equipment->getMaterial());
        }

        if ($equipment instanceof Maku) {
            return $equipment->getMaterial() ?? '';
        }

        if ($equipment instanceof Muneate) {
            return $equipment->getMaterial() ?? '';
        }

        if ($equipment instanceof Shitagake) {
            return $equipment->getMaterial() ?? '';
        }

        if ($equipment instanceof Yumi) {
            return $equipment->getMaterial() ?? '';
        }

        return '';
    }

    private function extractYumiStrength(Equipment $equipment): string
    {
        if (!$equipment instanceof Yumi) {
            return '';
        }

        return (string) ($equipment->getStrength() ?? '');
    }

    private function extractTsuruStrengthMin(Equipment $equipment): string
    {
        if (!$equipment instanceof Tsuru) {
            return '';
        }

        return (string) ($equipment->getStrengthMin() ?? '');
    }

    private function extractTsuruStrengthMax(Equipment $equipment): string
    {
        if (!$equipment instanceof Tsuru) {
            return '';
        }

        return (string) ($equipment->getStrengthMax() ?? '');
    }

    private function extractTsuruLength(Equipment $equipment): string
    {
        if (!$equipment instanceof Tsuru) {
            return '';
        }

        return $this->extractEnumValue($equipment->getTsuruLength());
    }

    private function extractYumiLength(Equipment $equipment): string
    {
        if (!$equipment instanceof Yumi) {
            return '';
        }

        return $this->extractEnumValue($equipment->getYumiLength());
    }

    private function extractNbFingers(Equipment $equipment): string
    {
        if ($equipment instanceof Gake) {
            return (string) ($equipment->getNbFingers() ?? '');
        }

        if ($equipment instanceof Shitagake) {
            return (string) ($equipment->getNbFingers() ?? '');
        }

        return '';
    }

    private function extractSize(Equipment $equipment): string
    {
        if ($equipment instanceof Gake) {
            return (string) ($equipment->getSize() ?? '');
        }

        if ($equipment instanceof Muneate) {
            return $equipment->getSize() ?? '';
        }

        if ($equipment instanceof Shitagake) {
            return $equipment->getSize() ?? '';
        }

        return '';
    }

    private function extractHeight(Equipment $equipment): string
    {
        if ($equipment instanceof SupportMakiwara) {
            return (string) ($equipment->getHeight() ?? '');
        }

        if ($equipment instanceof Maku) {
            return (string) ($equipment->getHeight() ?? '');
        }

        return '';
    }

    private function extractNbBows(Equipment $equipment): string
    {
        if (!$equipment instanceof Yumitate) {
            return '';
        }

        return (string) ($equipment->getNbBows() ?? '');
    }

    private function extractYumitateOrientation(Equipment $equipment): string
    {
        if (!$equipment instanceof Yumitate) {
            return '';
        }

        return $this->extractEnumValue($equipment->getOrientation());
    }

    private function extractNbArrows(Equipment $equipment): string
    {
        if (!$equipment instanceof Yatate) {
            return '';
        }

        return (string) ($equipment->getNbArrows() ?? '');
    }

    private function extractLength(Equipment $equipment): string
    {
        if ($equipment instanceof Maku) {
            return (string) ($equipment->getEquipmentLength() ?? '');
        }

        if ($equipment instanceof Etafoam) {
            return (string) ($equipment->getEquipmentLength() ?? '');
        }

        return '';
    }

    private function extractEtafoamWidth(Equipment $equipment): string
    {
        if (!$equipment instanceof Etafoam) {
            return '';
        }

        return (string) ($equipment->getWidth() ?? '');
    }

    private function extractEtafoamThickness(Equipment $equipment): string
    {
        if (!$equipment instanceof Etafoam) {
            return '';
        }

        return (string) ($equipment->getThickness() ?? '');
    }

    private function extractQuantity(Equipment $equipment): string
    {
        if ($equipment instanceof Etafoam) {
            return (string) $equipment->getQuantity();
        }

        if ($equipment instanceof Muneate) {
            return (string) $equipment->getQuantity();
        }

        if ($equipment instanceof Shitagake) {
            return (string) $equipment->getQuantity();
        }

        if ($equipment instanceof Tsuru) {
            return (string) $equipment->getQuantity();
        }

        return '';
    }

    private function extractMakuWeight(Equipment $equipment): string
    {
        if (!$equipment instanceof Maku) {
            return '';
        }

        return (string) ($equipment->getWeight() ?? '');
    }

    private function extractMakuAttachment(Equipment $equipment): string
    {
        if (!$equipment instanceof Maku) {
            return '';
        }

        return $equipment->getAttachment() ?? '';
    }

    private function extractEnumValue(?\BackedEnum $enum): string
    {
        if (!$enum instanceof \BackedEnum) {
            return '';
        }

        return (string) $enum->value;
    }

    /**
     * Encode une ligne CSV en échappant les valeurs qui contiennent le séparateur ou des guillemets.
     *
     * @param string[] $row
     */
    private function encodeRow(array $row): string
    {
        $escaped = array_map(function (string $value): string {
            // Échapper les guillemets doubles
            $value = str_replace('"', '""', $value);

            // Encadrer par des guillemets si nécessaire
            if (str_contains($value, self::SEPARATOR)
                || str_contains($value, '"')
                || str_contains($value, "\n")
            ) {
                $value = '"'.$value.'"';
            }

            return $value;
        }, $row);

        return implode(self::SEPARATOR, $escaped);
    }
}
