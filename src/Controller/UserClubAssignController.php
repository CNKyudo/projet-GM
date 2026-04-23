<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Form\UserClubAssignType;
use App\Security\Voter\UserPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserClubAssignController extends AbstractController
{
    #[Route('/user/{id}/club', name: 'user_assign_club', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(UserPermissionVoter::ASSIGN_USER_TO_ANY_CLUB)]
    public function assignClub(Request $request, User $targetUser, EntityManagerInterface $entityManager): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $form = $this->createForm(UserClubAssignType::class, $targetUser, [
            'current_user' => $currentUser,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Club mis à jour pour cet utilisateur.');

            return $this->redirectToRoute('user_index');
        }

        return $this->render('user/assign_club.html.twig', [
            'targetUser' => $targetUser,
            'form'       => $form,
            'roleLabels' => UserRole::labelsFromStrings($targetUser->getRoles()),
        ]);
    }
}
