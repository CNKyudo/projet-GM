<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AdminUserCreateType;
use App\Service\PasswordGenerator;
use App\Security\Voter\UserPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly PasswordGenerator $passwordGenerator,
    ) {
    }

    /**
     * Création d'un utilisateur par un administrateur.
     *
     * Un mot de passe aléatoire est généré (12 caractères, 1 majuscule, 1 caractère spécial).
     * L'utilisateur est créé avec ROLE_USER et mustChangePassword = true afin d'être
     * contraint de définir son propre mot de passe à la première connexion.
     */
    #[Route('/admin/users/create', name: 'admin_user_create', methods: ['GET', 'POST'])]
    #[IsGranted(UserPermissionVoter::CREATE_USER)]
    public function createUser(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(AdminUserCreateType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $this->passwordGenerator->generate();
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $plainPassword));
            $user->setMustChangePassword(true);
            $user->setRoles([]);

            $entityManager->persist($user);
            $entityManager->flush();

            $request->getSession()->set('admin_user_created', [
                'email' => $user->getEmail(),
                'plainPassword' => $plainPassword,
            ]);

            return $this->redirectToRoute('admin_user_create_success');
        }

        return $this->render('admin/user/create.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Page de confirmation affichant les identifiants du nouvel utilisateur.
     *
     * Affiche l'email et le mot de passe généré qui doivent être communiqués à l'utilisateur.
     */
    #[Route('/admin/users/create/success', name: 'admin_user_create_success', methods: ['GET'])]
    #[IsGranted(UserPermissionVoter::CREATE_USER)]
    public function createSuccess(Request $request): Response
    {
        $sessionData = $request->getSession()->get('admin_user_created');

        if (!$sessionData) {
            return $this->redirectToRoute('admin_user_create');
        }

        $request->getSession()->remove('admin_user_created');

        return $this->render('admin/user/create-success.html.twig', [
            'email' => $sessionData['email'],
            'plainPassword' => $sessionData['plainPassword'],
        ]);
    }
}
