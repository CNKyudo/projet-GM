<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Equipment;
use App\Entity\QRCode;
// use BaconQrCode\Renderer\Image\SvgImageBackEnd;
// use BaconQrCode\Renderer\ImageRenderer;
// use BaconQrCode\Renderer\RendererStyle\RendererStyle;
// use BaconQrCode\Writer;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\PngWriter;
// new start
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
// new end
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class QRCodeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
    ) {
    }

    /**
     * Crée un QR code pour un équipement et le persiste.
     */
    public function createForEquipment(Equipment $equipment): QRCode
    {
        $qrCode = new QRCode();
        $qrCode->setEquipment($equipment);

        $this->em->persist($qrCode);
        $this->em->flush();

        return $qrCode;
    }

    /**
     * Génère un QR code stylisé en PNG (via endroid/qr-code + GD) pour PDF.
     */
    private function generateStyledQrCode(string $url): string
    {
        $writer = new PngWriter();

        $qrCode = new EndroidQrCode(
            data:  $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );

        $logo = new Logo(
            path: __DIR__.'/../../assets/pictures/FRANCE_KYUDO_LOGOTYPE_RVB_PRINCIPAL BLANC.png',
            resizeToWidth: 150,
            punchoutBackground: true
        );

        $result = $writer->write($qrCode, $logo);

        return base64_encode($result->getString());
    }

    /**
     * Génère le SVG du QR code à partir de son UUID.
     * Le contenu encodé est l'URL de scan publique.
     */
    public function generateSvg(QRCode $qrCode, int $size = 200): string
    {
        $url = $this->urlGenerator->generate(
            'qr_code.scan',
            ['uuid' => $qrCode->getUuid()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $qr = new EndroidQrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(10, 0, 150),
            backgroundColor: new Color(255, 255, 255)
        );

        $logo = new Logo(
            path: __DIR__.'/../../assets/pictures/FRANCE_KYUDO_LOGOTYPE_RVB_PRINCIPAL BLANC_BG-BLEU.png',
            resizeToWidth: (int) ($size * 0.5)
        );

        $writer = new SvgWriter();

        $result = $writer->write(
            $qr,
            $logo
        );

        return $result->getString();
    }

    /**
     * Génère un PDF contenant le QR code en PNG (via endroid/qr-code + GD) et les infos de l'équipement.
     * Le PNG est encodé en base64 et intégré directement dans le HTML (pas de fichier temporaire).
     * Retourne le contenu binaire du PDF.
     */
    public function generatePdf(QRCode $qrCode): string
    {
        $equipment = $qrCode->getEquipment();

        $equipmentLabel = $equipment instanceof Equipment
            ? sprintf('#%d - %s', $equipment->getId(), ucfirst($equipment->getTypeName()))
            : 'Équipement inconnu';

        $ownerLabel = ($equipment && $equipment->getOwnerClub())
            ? htmlspecialchars((string) $equipment->getOwnerClub()->getName())
            : '-';

        $url = $this->urlGenerator->generate(
            'qr_code.scan',
            ['uuid' => $qrCode->getUuid()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Génération PNG via endroid/qr-code (utilise GD, pas Imagick)
        $pngBase64 = $this->generateStyledQrCode($url);

        $html = $this->twig->render('qr-code.pdf.twig', [
            'equipmentLabel' => $equipmentLabel,
            'ownerLabel' => $ownerLabel,
            'pngBase64' => $pngBase64,
            'url' => $url,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->setChroot([sys_get_temp_dir()]);
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Change l'équipement associé à un QR code existant.
     */
    public function changeEquipment(QRCode $qrCode, Equipment $newEquipment): QRCode
    {
        // Détacher l'ancien équipement
        $oldEquipment = $qrCode->getEquipment();
        if ($oldEquipment instanceof Equipment && $oldEquipment !== $newEquipment) {
            $oldEquipment->setQrCode(null);
        }

        $qrCode->setEquipment($newEquipment);
        $this->em->flush();

        return $qrCode;
    }

    /**
     * Supprime un QR code.
     */
    public function delete(QRCode $qrCode): void
    {
        $this->em->remove($qrCode);
        $this->em->flush();
    }
}
