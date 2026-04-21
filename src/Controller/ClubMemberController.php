<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Club;
use App\Entity\ClubMember;
use App\Form\ClubMemberFormType;
use App\Repository\ClubMemberRepository;
use App\Security\Voter\UserPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ClubMemberController extends AbstractController
{
    #[Route('/club/{id}/member', name: 'club_member_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function create(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(UserPermissionVoter::CREATE_CLUB_MEMBER, $club);

        $clubMember = new ClubMember();
        $clubMember->setClub($club);

        $form = $this->createForm(ClubMemberFormType::class, $clubMember);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($clubMember);
            $entityManager->flush();
            $this->addFlash('success', 'Membre ajouté.');
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
        }

        return $this->redirectToRoute('club_show', ['id' => $club->getId()]);
    }

    #[Route('/club/{clubId}/member/{memberId}/edit', name: 'club_member_edit', requirements: ['clubId' => '\d+', 'memberId' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        int $clubId,
        int $memberId,
        ClubMemberRepository $clubMemberRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $clubMember = $clubMemberRepository->find($memberId);
        if (!$clubMember instanceof ClubMember || $clubMember->getClub()->getId() !== $clubId) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(UserPermissionVoter::EDIT_CLUB_MEMBER, $clubMember);

        $form = $this->createForm(ClubMemberFormType::class, $clubMember);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Membre mis à jour.');

            return $this->redirectToRoute('club_show', ['id' => $clubId]);
        }

        return $this->render('club/member_edit.html.twig', [
            'club'       => $clubMember->getClub(),
            'clubMember' => $clubMember,
            'form'       => $form,
        ]);
    }

    #[Route('/club/{clubId}/member/{memberId}/delete', name: 'club_member_delete', requirements: ['clubId' => '\d+', 'memberId' => '\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        int $clubId,
        int $memberId,
        ClubMemberRepository $clubMemberRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $clubMember = $clubMemberRepository->find($memberId);
        if (!$clubMember instanceof ClubMember || $clubMember->getClub()->getId() !== $clubId) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(UserPermissionVoter::DELETE_CLUB_MEMBER, $clubMember);

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_member'.$clubMember->getId(), $token)) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('club_show', ['id' => $clubId]);
        }

        if (!$clubMember->canBeDeleted()) {
            $this->addFlash('danger', 'Impossible de supprimer ce membre : il a du matériel emprunté. Veuillez d\'abord récupérer le matériel.');

            return $this->redirectToRoute('club_show', ['id' => $clubId]);
        }

        $entityManager->remove($clubMember);
        $entityManager->flush();
        $this->addFlash('success', 'Membre supprimé.');

        return $this->redirectToRoute('club_show', ['id' => $clubId]);
    }
}
