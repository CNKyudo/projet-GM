<?php

namespace App\Service;

use App\Entity\Equipment;
use App\Entity\QRCode;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\PngWriter;
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

        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }

    /**
     * Génère un PDF contenant le QR code en PNG (via endroid/qr-code + GD) et les infos de l'équipement.
     * Le PNG est écrit dans un fichier temporaire référencé via file:// pour Dompdf.
     * Retourne le contenu binaire du PDF.
     */
    public function generatePdf(QRCode $qrCode): string
    {
        $equipment = $qrCode->getEquipment();

        $equipmentLabel = $equipment
            ? sprintf('#%d - %s', $equipment->getId(), ucfirst($equipment->getTypeName()))
            : 'Équipement inconnu';

        $ownerLabel = ($equipment && $equipment->getOwnerClub())
            ? htmlspecialchars($equipment->getOwnerClub()->getName())
            : '-';

        $url = $this->urlGenerator->generate(
            'qr_code.scan',
            ['uuid' => $qrCode->getUuid()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Génération PNG via endroid/qr-code (utilise GD, pas Imagick)
        $endroidQr = new EndroidQrCode(data: $url, size: 300, margin: 10);
        $pngContent = (new PngWriter())->write($endroidQr)->getString();

        // Fichier temporaire PNG référencé via file:// par Dompdf
        $tmpFile = tempnam(sys_get_temp_dir(), 'qr_').'.png';
        file_put_contents($tmpFile, $pngContent);

        try {
            $html = $this->twig->render('qr-code.pdf.twig', [
                'equipmentLabel' => $equipmentLabel,
                'ownerLabel' => $ownerLabel,
                'tmpFile' => $tmpFile,
                'url' => $url,
            ]);

            $options = new Options();
            $options->set('defaultFont', 'Arial');
            $options->setChroot([sys_get_temp_dir(), '/var/www/project']);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return $dompdf->output();
        } finally {
            // Nettoyage garanti même en cas d'exception
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * Change l'équipement associé à un QR code existant.
     */
    public function changeEquipment(QRCode $qrCode, Equipment $newEquipment): QRCode
    {
        // Détacher l'ancien équipement
        $oldEquipment = $qrCode->getEquipment();
        if (null !== $oldEquipment && $oldEquipment !== $newEquipment) {
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
