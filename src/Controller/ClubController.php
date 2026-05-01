<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Club;
use App\Entity\ClubMember;
use App\Form\ClubMemberFormType;
use App\Form\ClubType;
use App\Repository\ClubRepository;
use App\Security\Voter\UserPermissionVoter;
use App\Service\ClubRoleManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ClubController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly ClubRepository $clubRepository,
        private readonly PaginatorInterface $paginator,
        private readonly ClubRoleManager $clubRoleManager,
    ) {
    }

    #[Route('/club/', name: 'club_index', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(Request $request): Response
    {
        $queryBuilder = $this->clubRepository->createQueryBuilder('c')->orderBy('c.id', 'DESC');

        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE,
        );

        return $this->render('club/index.html.twig', [
            'clubs' => $pagination,
        ]);
    }

    #[Route('/club/new', name: 'club_new', methods: ['GET', 'POST'])]
    #[IsGranted(UserPermissionVoter::CREATE_CLUB)]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $club = new Club();
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->clubRoleManager->syncClubRoles(null, $club->getPresident(), null, $club->getEquipmentManager());
                $entityManager->persist($club);
                $entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                $this->addUniqueConstraintError($form, $e);

                return $this->render('club/new.html.twig', [
                    'form' => $form,
                ]);
            }

            $this->addFlash('success', 'Club créé.');

            return $this->redirectToRoute('club_index');
        }

        return $this->render('club/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/club/{id}', name: 'club_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(Club $club): Response
    {
        $memberForm = null;
        if ($this->isGranted(UserPermissionVoter::CREATE_CLUB_MEMBER, $club)) {
            $memberForm = $this->createForm(ClubMemberFormType::class, new ClubMember(), [
                'action' => $this->generateUrl('club_member_create', ['id' => $club->getId()]),
                'method' => 'POST',
            ]);
        }

        return $this->render('club/show.html.twig', [
            'club' => $club,
            'memberForm' => $memberForm?->createView(),
        ]);
    }

    #[Route('/club/{id}/edit', name: 'club_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(UserPermissionVoter::EDIT_CLUB, $club);

        $previousPresident = $club->getPresident();
        $previousManager   = $club->getEquipmentManager();

        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->clubRoleManager->syncClubRoles($previousPresident, $club->getPresident(), $previousManager, $club->getEquipmentManager());
                $entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                $this->addUniqueConstraintError($form, $e);

                return $this->render('club/edit.html.twig', [
                    'club' => $club,
                    'form' => $form,
                ]);
            }

            $this->addFlash('success', 'Club mis à jour.');

            return $this->redirectToRoute('club_index');
        }

        return $this->render('club/edit.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/club/{id}', name: 'club_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(UserPermissionVoter::DELETE_CLUB, $club);

        $token = (string) $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete'.$club->getId(), $token)) {
            $entityManager->remove($club);
            $entityManager->flush();
            $this->addFlash('success', 'Club supprimé.');
        }

        return $this->redirectToRoute('club_index');
    }

    /** @param FormInterface<mixed> $form */
    private function addUniqueConstraintError(FormInterface $form, UniqueConstraintViolationException $uniqueConstraintViolationException): void
    {
        // Identifier le champ fautif à partir du message SQL (colonne impliquée)
        $message = $uniqueConstraintViolationException->getMessage();

        if (str_contains($message, 'equipmentManager')) {
            $this->addEquipmentManagerAlreadyAssignedError($form);
        } else {
            $this->addPresidentAlreadyAssignedError($form);
        }
    }

    /** @param FormInterface<mixed> $form */
    private function addPresidentAlreadyAssignedError(FormInterface $form): void
    {
        $message = 'Cet utilisateur est déjà président d\'un club.';

        if ($form->has('president')) {
            $form->get('president')->addError(new FormError($message));

            return;
        }

        $form->addError(new FormError($message));
    }

    /** @param FormInterface<mixed> $form */
    private function addEquipmentManagerAlreadyAssignedError(FormInterface $form): void
    {
        $message = 'Cet utilisateur est déjà gestionnaire matériel d\'un club.';

        if ($form->has('equipmentManager')) {
            $form->get('equipmentManager')->addError(new FormError($message));

            return;
        }

        $form->addError(new FormError($message));
    }
}
