<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Form\ChangePasswordFormType;
use App\Form\UserProfileType;
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
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('/profile', name: 'user_profile', methods: ['GET'])]
    #[IsGranted(UserPermissionVoter::EDIT_OWN_ACCOUNT_INFORMATION)]
    public function profile(): Response
    {
        $user = $this->getAuthenticatedUser();

        return $this->render('user/profile.html.twig', [
            'user'       => $user,
            'roleLabels' => UserRole::labelsFromStrings($user->getRoles()),
        ]);
    }

    #[Route('/profile/edit', name: 'user_profile_edit', methods: ['GET', 'POST'])]
    #[IsGranted(UserPermissionVoter::EDIT_OWN_ACCOUNT_INFORMATION)]
    public function editProfile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getAuthenticatedUser();
        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Vos informations ont bien été mises à jour.');

            return $this->redirectToRoute('user_profile');
        }

        return $this->render('user/edit_profile.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/profile/password', name: 'user_change_password', methods: ['GET', 'POST'])]
    #[IsGranted(UserPermissionVoter::EDIT_OWN_ACCOUNT_INFORMATION)]
    public function changePassword(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getAuthenticatedUser();
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($this->userPasswordHasher->hashPassword($user, $plainPassword));
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a bien été modifié.');

            return $this->redirectToRoute('user_profile');
        }

        return $this->render('user/change_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/user', name: 'user_index', methods: ['GET'])]
    #[IsGranted(UserPermissionVoter::ACCESS_USER_MANAGEMENT)]
    public function index(): Response
    {
        $users = $this->userRepository->findBy([], ['email' => 'ASC']);
        $roleLabelsByUserId = [];
        foreach ($users as $user) {
            $roleLabelsByUserId[(int) $user->getId()] = UserRole::labelsFromStrings($user->getRoles());
        }

        return $this->render('user/index.html.twig', [
            'users'              => $users,
            'roleLabelsByUserId' => $roleLabelsByUserId,
        ]);
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        return $user;
    }
}
