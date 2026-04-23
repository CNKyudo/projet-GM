<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\RoleAssignDTO;
use App\Entity\Club;
use App\Entity\User;
use App\Enum\UserRole;
use App\Form\UserRoleAssignType;
use App\Repository\ClubRepository;
use App\Repository\RegionRepository;
use App\Security\UserPermissionService;
use App\Security\Voter\UserPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserRoleAssignController extends AbstractController
{
    public function __construct(
        private readonly UserPermissionService $userPermissionService,
        private readonly ClubRepository $clubRepository,
        private readonly RegionRepository $regionRepository,
    ) {
    }

    #[Route('/user/{id}/role', name: 'user_assign_role', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(UserPermissionVoter::ASSIGN_USER_ROLE)]
    public function assignRole(Request $request, User $targetUser, EntityManagerInterface $entityManager): Response
    {
        $currentUser = $this->getAuthenticatedUser();

        $assignableRoles = $this->userPermissionService->getAssignableRoles($currentUser);

        if ([] === $assignableRoles) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour affecter un rôle.');
        }

        $dto = new RoleAssignDTO();
        $form = $this->createForm(UserRoleAssignType::class, $dto, [
            'assignable_roles'     => $assignableRoles,
            'club_query_builder'   => $this->buildClubQueryBuilder($currentUser),
            'region_query_builder' => $this->buildRegionQueryBuilder($currentUser),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newRoleValue = $dto->newRole;
            $newRole      = UserRole::tryFrom($newRoleValue ?? '');

            if (!$newRole instanceof UserRole) {
                $this->addFlash('error', 'Rôle invalide.');

                return $this->redirectToRoute('user_index');
            }

            // Vérification supplémentaire : le rôle est bien dans les droits du connecté
            if (!\in_array($newRole, $assignableRoles, true)) {
                throw $this->createAccessDeniedException('Vous ne pouvez pas affecter ce rôle.');
            }

            $this->applyRoleAssignment($targetUser, $newRole, $dto);
            $entityManager->flush();

            $this->addFlash('success', sprintf(
                'Le rôle « %s » a bien été attribué à %s.',
                $newRole->label(),
                $targetUser->getFullName()
            ));

            return $this->redirectToRoute('user_index');
        }

        return $this->render('user/assign_role.html.twig', [
            'targetUser'      => $targetUser,
            'form'            => $form,
            'roleLabels'      => UserRole::labelsFromStrings($targetUser->getRoles()),
            'assignableRoles' => $assignableRoles,
        ]);
    }

    /**
     * Applique le changement de rôle :
     *  1. Nettoie toutes les anciennes affectations (présidence, gestionnaire, régions)
     *  2. Définit le nouveau rôle unique de l'utilisateur
     *  3. Applique les associations liées au nouveau rôle (club ou régions)
     */
    private function applyRoleAssignment(User $targetUser, UserRole $newRole, RoleAssignDTO $dto): void
    {
        // Nettoyage des anciennes affectations
        $targetUser->setClubWhichImPresidentOf(null);
        $targetUser->setClubWhereImEquipmentManager(null);

        foreach ($targetUser->getManagedRegions()->toArray() as $region) {
            $targetUser->removeManagedRegion($region);
        }

        // Rôle unique : on remplace intégralement (ROLE_USER est toujours ajouté par getRoles())
        $targetUser->setRoles([$newRole->value]);

        // Associations spécifiques au nouveau rôle
        match ($newRole) {
            UserRole::CLUB_PRESIDENT         => $targetUser->setClubWhichImPresidentOf($dto->club),
            UserRole::EQUIPMENT_MANAGER_CLUB => $targetUser->setClubWhereImEquipmentManager($dto->club),
            UserRole::EQUIPMENT_MANAGER_CTK  => $this->applyManagedRegions($targetUser, $dto),
            default                          => null,
        };
    }

    private function applyManagedRegions(User $targetUser, RoleAssignDTO $dto): void
    {
        foreach ($dto->managedRegions as $region) {
            $targetUser->addManagedRegion($region);
        }
    }

    /**
     * Construit le QueryBuilder des clubs accessibles au connecté.
     *
     *  - ADMIN / CN      → tous les clubs
     *  - CTK             → clubs de ses régions gérées
     *  - PRESIDENT       → uniquement son propre club (présidence)
     *  - MANAGER_CLUB    → uniquement son propre club (gestionnaire)
     */
    private function buildClubQueryBuilder(User $currentUser): ?QueryBuilder
    {
        if ([] === $this->userPermissionService->getAssignableRoles($currentUser)) {
            return null;
        }

        $qb    = $this->clubRepository->createQueryBuilder('c')->orderBy('c.name', 'ASC');
        $roles = $currentUser->getRoles();

        if (\in_array(UserRole::ADMIN->value, $roles, true)
            || \in_array(UserRole::EQUIPMENT_MANAGER_CN->value, $roles, true)) {
            return $qb;
        }

        if (\in_array(UserRole::EQUIPMENT_MANAGER_CTK->value, $roles, true)) {
            $regionIds = $currentUser->getManagedRegions()->map(fn ($r): ?int => $r->getId())->toArray();
            if ([] !== $regionIds) {
                $qb->join('c.region', 'r')->andWhere('r.id IN (:regionIds)')->setParameter('regionIds', $regionIds);
            }

            return $qb;
        }

        $ownClub = $currentUser->getClubWhichImPresidentOf()
            ?? $currentUser->getClubWhereImEquipmentManager();

        if ($ownClub instanceof Club) {
            $qb->andWhere('c.id = :clubId')->setParameter('clubId', $ownClub->getId());
        }

        return $qb;
    }

    /**
     * Construit le QueryBuilder des régions accessibles au connecté.
     *
     *  - ADMIN / CN → toutes les régions
     *  - CTK        → uniquement ses régions gérées
     *  - Autres     → toutes les régions (pas de restriction sur ce droit)
     */
    private function buildRegionQueryBuilder(User $currentUser): QueryBuilder
    {
        $qb    = $this->regionRepository->createQueryBuilder('r')->orderBy('r.name', 'ASC');
        $roles = $currentUser->getRoles();

        if (\in_array(UserRole::ADMIN->value, $roles, true)
            || \in_array(UserRole::EQUIPMENT_MANAGER_CN->value, $roles, true)) {
            return $qb;
        }

        if (\in_array(UserRole::EQUIPMENT_MANAGER_CTK->value, $roles, true)) {
            $regionIds = $currentUser->getManagedRegions()->map(fn ($r): ?int => $r->getId())->toArray();
            if ([] !== $regionIds) {
                $qb->andWhere('r.id IN (:regionIds)')->setParameter('regionIds', $regionIds);
            }

            return $qb;
        }

        return $qb;
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
