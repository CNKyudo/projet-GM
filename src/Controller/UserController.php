<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\UserClubAssignType;
use App\Repository\UserRepository;
use App\Security\Voter\UserPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserController extends AbstractController
{
    #[Route('/profile/password', name: 'user_change_password', methods: ['GET', 'POST'])]
    #[IsGranted(UserPermissionVoter::EDIT_OWN_ACCOUNT_INFORMATION)]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a bien été modifié.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('user/change_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/user', name: 'user_index', methods: ['GET'])]
    #[IsGranted(UserPermissionVoter::ACCESS_USER_MANAGEMENT)]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findBy([], ['email' => 'ASC']),
        ]);
    }

    #[Route('/user/{id}/club', name: 'user_assign_club', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted(UserPermissionVoter::ASSIGN_USER_TO_ANY_CLUB)]
    public function assignClub(Request $request, User $targetUser, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserClubAssignType::class, $targetUser);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Club mis à jour pour cet utilisateur.');

            return $this->redirectToRoute('user_index');
        }

        return $this->render('user/assign_club.html.twig', [
            'targetUser' => $targetUser,
            'form' => $form,
        ]);
    }
}
