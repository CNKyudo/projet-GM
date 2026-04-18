<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\QRCode;
use App\Form\QRCodeFormType;
use App\Repository\EquipmentRepository;
use App\Repository\QRCodeRepository;
use App\Security\Voter\UserPermissionVoter;
use App\Service\QRCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/qr-code')]
final class QRCodeController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly QRCodeService $qrCodeService,
    ) {
    }

    /**
     * Génère directement un QR code pour un équipement donné (depuis la page équipement).
     */
    #[Route('/equipment/{equipmentId}/generate-qr', name: 'qr_code.generate_for_equipment', requirements: ['equipmentId' => '\d+'], methods: ['POST'])]
    #[IsGranted(UserPermissionVoter::CREATE_QRCODE)]
    public function generateForEquipment(int $equipmentId, Request $request, EquipmentRepository $equipmentRepository): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $equipment = $equipmentRepository->find($equipmentId);

        if (null === $equipment) {
            throw $this->createNotFoundException('Équipement introuvable.');
        }

        if (!$this->isCsrfTokenValid('generate_qr_'.$equipmentId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('equipment.show', ['id' => $equipmentId]);
        }

        if (null !== $equipment->getQrCode()) {
            $this->addFlash('error', 'Cet équipement possède déjà un QR code.');

            return $this->redirectToRoute('equipment.show', ['id' => $equipmentId]);
        }

        $qrCode = $this->qrCodeService->createForEquipment($equipment);

        $this->addFlash('success', 'QR code généré avec succès.');

        return $this->redirectToRoute('qr_code.show', ['uuid' => $qrCode->getUuid()]);
    }

    /**
     * Route de SCAN : redirige vers la page de l'équipement associé.
     * Accessible à tous les utilisateurs authentifiés.
     * IMPORTANT : cette route doit être déclarée AVANT les routes préfixées /qr-code/{uuid}/...
     */
    #[Route('/{uuid}', name: 'qr_code.scan', requirements: ['uuid' => '[0-9a-f\-]{36}'], methods: ['GET'], priority: 10)]
    public function scan(string $uuid, QRCodeRepository $repository): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $qrCode = $repository->findByUuid($uuid);

        if (!$qrCode instanceof QRCode || !$qrCode->getEquipment() instanceof \App\Entity\Equipment) {
            throw $this->createNotFoundException('QR code introuvable ou équipement supprimé.');
        }

        return $this->redirectToRoute('equipment.show', [
            'id' => $qrCode->getEquipment()->getId(),
        ]);
    }

    /**
     * Liste paginée de tous les QR codes.
     */
    #[Route('', name: 'qr_code.index', methods: ['GET'])]
    #[IsGranted(UserPermissionVoter::VIEW_QRCODE)]
    public function index(QRCodeRepository $repository, PaginatorInterface $paginator, Request $request): Response
    {
        $qrCodes = $paginator->paginate(
            $repository->createQueryBuilder('q')
                ->leftJoin('q.equipment', 'e')
                ->addSelect('e')
                ->orderBy('q.createdAt', 'DESC'),
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE,
        );

        return $this->render('qr_code/index.html.twig', [
            'qrCodes' => $qrCodes,
        ]);
    }

    /**
     * Affiche les détails d'un QR code avec son SVG.
     */
    #[Route('/{uuid}/show', name: 'qr_code.show', requirements: ['uuid' => '[0-9a-f\-]{36}'], methods: ['GET'])]
    #[IsGranted(UserPermissionVoter::VIEW_QRCODE)]
    public function show(string $uuid, QRCodeRepository $repository): Response
    {
        $qrCode = $repository->findByUuid($uuid);

        if (!$qrCode instanceof QRCode) {
            throw $this->createNotFoundException('QR code introuvable.');
        }

        $svg = $this->qrCodeService->generateSvg($qrCode);

        return $this->render('qr_code/show.html.twig', [
            'qrCode' => $qrCode,
            'svg' => $svg,
        ]);
    }

    /**
     * Création d'un nouveau QR code.
     */
    #[Route('/new', name: 'qr_code.new', methods: ['GET', 'POST'])]
    #[IsGranted(UserPermissionVoter::CREATE_QRCODE)]
    public function new(Request $request): Response
    {
        $qrCode = new QRCode();
        $form = $this->createForm(QRCodeFormType::class, $qrCode);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $equipment = $form->get('equipment')->getData();
            $createdQrCode = $this->qrCodeService->createForEquipment($equipment);

            $this->addFlash('success', 'QR code généré avec succès.');

            return $this->redirectToRoute('qr_code.show', ['uuid' => $createdQrCode->getUuid()]);
        }

        return $this->render('qr_code/form.html.twig', [
            'form' => $form,
            'qrCode' => null,
            'title' => 'Nouveau QR code',
        ]);
    }

    /**
     * Modification de l'équipement associé à un QR code.
     */
    #[Route('/{uuid}/edit', name: 'qr_code.edit', requirements: ['uuid' => '[0-9a-f\-]{36}'], methods: ['GET', 'POST'])]
    #[IsGranted(UserPermissionVoter::EDIT_QRCODE)]
    public function edit(string $uuid, Request $request, QRCodeRepository $repository, EntityManagerInterface $em): Response
    {
        $qrCode = $repository->findByUuid($uuid);

        if (!$qrCode instanceof QRCode) {
            throw $this->createNotFoundException('QR code introuvable.');
        }

        $form = $this->createForm(QRCodeFormType::class, $qrCode, [
            'current_equipment' => $qrCode->getEquipment(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newEquipment = $form->get('equipment')->getData();
            $this->qrCodeService->changeEquipment($qrCode, $newEquipment);

            $this->addFlash('success', 'QR code mis à jour.');

            return $this->redirectToRoute('qr_code.show', ['uuid' => $qrCode->getUuid()]);
        }

        return $this->render('qr_code/form.html.twig', [
            'form' => $form,
            'qrCode' => $qrCode,
            'title' => 'Modifier le QR code',
        ]);
    }

    /**
     * Suppression d'un QR code.
     */
    #[Route('/{uuid}/delete', name: 'qr_code.delete', requirements: ['uuid' => '[0-9a-f\-]{36}'], methods: ['POST'])]
    #[IsGranted(UserPermissionVoter::DELETE_QRCODE)]
    public function delete(string $uuid, Request $request, QRCodeRepository $repository): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $qrCode = $repository->findByUuid($uuid);

        if (!$qrCode instanceof QRCode) {
            throw $this->createNotFoundException('QR code introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_qr_code_'.$uuid, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('qr_code.index');
        }

        $this->qrCodeService->delete($qrCode);

        $this->addFlash('success', 'QR code supprimé.');

        return $this->redirectToRoute('qr_code.index');
    }

    /**
     * Sert le SVG du QR code comme image (utilisé dans les balises <img>).
     */
    #[Route('/{uuid}/svg', name: 'qr_code.svg', requirements: ['uuid' => '[0-9a-f\-]{36}'], methods: ['GET'])]
    #[IsGranted(UserPermissionVoter::VIEW_QRCODE)]
    public function svg(string $uuid, QRCodeRepository $repository): Response
    {
        $qrCode = $repository->findByUuid($uuid);

        if (!$qrCode instanceof QRCode) {
            throw $this->createNotFoundException('QR code introuvable.');
        }

        $svg = $this->qrCodeService->generateSvg($qrCode);

        return new Response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Téléchargement du PDF contenant le QR code.
     */
    #[Route('/{uuid}/download-pdf', name: 'qr_code.download_pdf', requirements: ['uuid' => '[0-9a-f\-]{36}'], methods: ['GET'])]
    #[IsGranted(UserPermissionVoter::VIEW_QRCODE)]
    public function downloadPdf(string $uuid, QRCodeRepository $repository): Response
    {
        $qrCode = $repository->findByUuid($uuid);

        if (!$qrCode instanceof QRCode) {
            throw $this->createNotFoundException('QR code introuvable.');
        }

        $pdfContent = $this->qrCodeService->generatePdf($qrCode);

        $equipment = $qrCode->getEquipment();
        $filename = sprintf(
            'qrcode-%s-%s.pdf',
            $equipment instanceof \App\Entity\Equipment ? $equipment->getId() : 'unknown',
            substr($qrCode->getUuid(), 0, 8)
        );

        return new Response(
            $pdfContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Content-Length' => strlen($pdfContent),
            ]
        );
    }
}
