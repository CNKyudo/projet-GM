<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Equipment;
use App\Entity\Gake;
use App\Entity\QRCode;
use App\Repository\EquipmentRepository;
use App\Service\QRCodeService;

/**
 * Tests fonctionnels : QRCodeController.
 *
 * Routes testées :
 *   GET /qr-code/{uuid}/svg → qr_code.svg
 *
 * Le SVG du QR code doit être mis en cache par le navigateur :
 *   - Cache-Control: public, max-age=31536000
 *   - Pas de session / Set-Cookie
 */
final class QRCodeControllerTest extends AbstractWebTestCase
{
    private string $qrCodeUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        /** @var EquipmentRepository $repo */
        $repo = $container->get(EquipmentRepository::class);

        /** @var Equipment[] $gakes */
        $gakes = $repo->findAll();

        $equipment = null;
        foreach ($gakes as $gake) {
            // Prendre un gant Club A sans QR code existant
            if ($gake instanceof Gake && AppFixtures::CLUB_A === $gake->getOwnerClub()?->getName() && !$gake->getQrCode() instanceof QRCode) {
                $equipment = $gake;
                break;
            }
        }

        if (!$equipment instanceof Gake) {
            throw new \RuntimeException('Aucun équipement disponible sans QR code dans les fixtures. Nécessite de recréer les fixtures.');
        }

        /** @var QRCodeService $qrCodeService */
        $qrCodeService = $container->get(QRCodeService::class);
        $qrCode = $qrCodeService->createForEquipment($equipment);

        $this->qrCodeUuid = $qrCode->getUuid();
    }

    public function testSvgReturnsPublicCacheHeaders(): void
    {
        $this->client->request('GET', '/qr-code/'.$this->qrCodeUuid.'/svg');

        $response = $this->client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/svg+xml', $response->headers->get('Content-Type'));

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl, 'Cache-Control header should be present');
        $this->assertStringContainsString('public', $cacheControl, 'Cache-Control should allow public caching');
        $this->assertStringContainsString('max-age=31536000', $cacheControl, 'Cache-Control should set max-age=31536000');
    }

    public function testSvgDoesNotStartSession(): void
    {
        $this->client->request('GET', '/qr-code/'.$this->qrCodeUuid.'/svg');

        $response = $this->client->getResponse();

        // Aucun Set-Cookie ne doit être émis (pas de session démarrée)
        $this->assertNull(
            $response->headers->get('Set-Cookie'),
            'The SVG response must not set a session cookie, otherwise the browser will not cache it'
        );
    }

    public function testSvgAccessibleWithoutAuthentication(): void
    {
        // Ne PAS appeler loginAs() → accès anonyme
        $this->client->request('GET', '/qr-code/'.$this->qrCodeUuid.'/svg');

        $response = $this->client->getResponse();

        $this->assertSame(200, $response->getStatusCode(), 'The SVG route should be accessible without authentication');
    }
}
