<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;

final readonly class UserClubAssigner
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClubRoleManager $clubRoleManager,
    ) {
    }

    /**
     * @param FormInterface<User> $form
     */
    public function assign(User $targetUser, FormInterface $form): void
    {
        $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($targetUser);

        $prevPresidentClub = $originalData['clubWhichImPresidentOf'] ?? null;
        $prevManagerClub = $originalData['clubWhereImEquipmentManager'] ?? null;

        $this->handleInverseSideNull($targetUser, $form, $prevPresidentClub, $prevManagerClub);

        $newPresidentClub = $targetUser->getClubWhichImPresidentOf();
        $newManagerClub = $targetUser->getClubWhereImEquipmentManager();

        $previousPresident = $this->resolvePreviousPresident($newPresidentClub, $prevPresidentClub, $targetUser);
        $previousManager = $this->resolvePreviousManager($newManagerClub, $prevManagerClub, $targetUser);

        $this->clubRoleManager->syncClubRoles(
            $previousPresident,
            $newPresidentClub instanceof Club ? $targetUser : null,
            $previousManager,
            $newManagerClub instanceof Club ? $targetUser : null,
        );

        if ($newPresidentClub instanceof Club) {
            $newPresidentClub->setPresident($targetUser);
        }

        if ($prevPresidentClub instanceof Club) {
            $prevPresidentClub->setPresident(null);
        }

        if ($newManagerClub instanceof Club) {
            $newManagerClub->setEquipmentManager($targetUser);
        }

        if ($prevManagerClub instanceof Club) {
            $prevManagerClub->setEquipmentManager(null);
        }
    }

    /**
     * @param FormInterface<User> $form
     */
    private function handleInverseSideNull(User $targetUser, FormInterface $form, ?Club $prevPresidentClub, ?Club $prevManagerClub): void
    {
        $submittedPresidentClub = $form->get('clubWhichImPresidentOf')->getData();
        $submittedManagerClub = $form->get('clubWhereImEquipmentManager')->getData();

        if (null === $submittedPresidentClub && $prevPresidentClub instanceof Club) {
            $targetUser->setClubWhichImPresidentOf(null);
        }

        if (null === $submittedManagerClub && $prevManagerClub instanceof Club) {
            $targetUser->setClubWhereImEquipmentManager(null);
        }
    }

    private function resolvePreviousPresident(?Club $newPresidentClub, ?Club $prevPresidentClub, User $targetUser): ?User
    {
        if ($newPresidentClub instanceof Club) {
            return $this->findPresidentOfClub($newPresidentClub);
        }

        return $prevPresidentClub instanceof Club ? $targetUser : null;
    }

    private function resolvePreviousManager(?Club $newManagerClub, ?Club $prevManagerClub, User $targetUser): ?User
    {
        if ($newManagerClub instanceof Club) {
            return $this->findEquipmentManagerOfClub($newManagerClub);
        }

        return $prevManagerClub instanceof Club ? $targetUser : null;
    }

    private function findPresidentOfClub(Club $club): ?User
    {
        return $this->entityManager
            ->createQuery('SELECT u FROM App\Entity\User u JOIN u.clubWhichImPresidentOf c WHERE c.id = :id')
            ->setParameter('id', $club->getId())
            ->getOneOrNullResult();
    }

    private function findEquipmentManagerOfClub(Club $club): ?User
    {
        return $this->entityManager
            ->createQuery('SELECT u FROM App\Entity\User u JOIN u.clubWhereImEquipmentManager c WHERE c.id = :id')
            ->setParameter('id', $club->getId())
            ->getOneOrNullResult();
    }
}
