<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\User;
use App\Enum\UserRole;

/**
 * Gère la synchronisation des rôles lors du changement de président
 * ou de gestionnaire matériel d'un club.
 *
 * Règles :
 *  - ROLE_CLUB_PRESIDENT et ROLE_EQUIPMENT_MANAGER_CLUB sont mutuellement exclusifs.
 *  - Quand l'un de ces rôles est retiré, l'utilisateur revient à ROLE_MEMBER.
 *  - Les rôles supérieurs (CTK, CN, ADMIN) ne sont jamais modifiés.
 */
final class ClubRoleManager
{
    /**
     * Synchronise les rôles après changement de président.
     *
     * @param ?User $previousPresident L'ancien président (avant la modification)
     * @param ?User $newPresident      Le nouveau président (après la modification)
     */
    public function syncPresidentRoles(?User $previousPresident, ?User $newPresident): void
    {
        if ($previousPresident instanceof User && $previousPresident !== $newPresident) {
            $this->revokeClubLevelRole($previousPresident, UserRole::CLUB_PRESIDENT);
        }

        if ($newPresident instanceof User && $newPresident !== $previousPresident) {
            $this->applyPresidentRole($newPresident);
        }
    }

    /**
     * Synchronise les rôles après changement de gestionnaire matériel.
     *
     * @param ?User $previousManager L'ancien gestionnaire (avant la modification)
     * @param ?User $newManager      Le nouveau gestionnaire (après la modification)
     */
    public function syncEquipmentManagerRoles(?User $previousManager, ?User $newManager): void
    {
        if ($previousManager instanceof User && $previousManager !== $newManager) {
            $this->revokeClubLevelRole($previousManager, UserRole::EQUIPMENT_MANAGER_CLUB);
        }

        if ($newManager instanceof User && $newManager !== $previousManager) {
            $this->applyEquipmentManagerRole($newManager);
        }
    }

    /**
     * Synchronise à la fois les rôles de président et de gestionnaire matériel
     * après soumission du formulaire club.
     */
    public function syncClubRoles(
        ?User $previousPresident,
        ?User $newPresident,
        ?User $previousManager,
        ?User $newManager,
    ): void {
        $this->syncPresidentRoles($previousPresident, $newPresident);
        $this->syncEquipmentManagerRoles($previousManager, $newManager);
    }

    // -----------------------------------------------------------------------
    // Méthodes privées
    // -----------------------------------------------------------------------

    /**
     * Attribue ROLE_CLUB_PRESIDENT.
     * Retire ROLE_EQUIPMENT_MANAGER_CLUB (mutuellement exclusif).
     * Ne touche jamais aux rôles supérieurs.
     */
    private function applyPresidentRole(User $user): void
    {
        if ($this->hasSuperiorRole($user)) {
            return;
        }

        $roles   = $this->stripBaseRoles($user->getRoles());
        $roles   = array_values(array_filter($roles, fn (string $r): bool => UserRole::EQUIPMENT_MANAGER_CLUB->value !== $r));
        $roles[] = UserRole::CLUB_PRESIDENT->value;

        $user->setRoles(array_values(array_unique($roles)));
    }

    /**
     * Attribue ROLE_EQUIPMENT_MANAGER_CLUB.
     * Retire ROLE_CLUB_PRESIDENT (mutuellement exclusif).
     * Ne touche jamais aux rôles supérieurs.
     */
    private function applyEquipmentManagerRole(User $user): void
    {
        if ($this->hasSuperiorRole($user)) {
            return;
        }

        $roles   = $this->stripBaseRoles($user->getRoles());
        $roles   = array_values(array_filter($roles, fn (string $r): bool => UserRole::CLUB_PRESIDENT->value !== $r));
        $roles[] = UserRole::EQUIPMENT_MANAGER_CLUB->value;

        $user->setRoles(array_values(array_unique($roles)));
    }

    /**
     * Retire le rôle club-level spécifié (ROLE_CLUB_PRESIDENT ou ROLE_EQUIPMENT_MANAGER_CLUB).
     * Le rôle est toujours retiré, même si l'utilisateur possède un rôle supérieur.
     * Si l'utilisateur n'a pas de rôle supérieur et n'a pas déjà ROLE_MEMBER,
     * ROLE_USER est remplacé par ROLE_MEMBER.
     */
    private function revokeClubLevelRole(User $user, UserRole $roleToRemove): void
    {
        $newRoles = array_values(array_filter($user->getRoles(), fn (string $role): bool => $role !== $roleToRemove->value));

        if (!$this->hasSuperiorRole($user) && !\in_array(UserRole::MEMBER->value, $newRoles, true)) {
            $newRoles = array_values(array_filter($newRoles, fn (string $r): bool => $r !== UserRole::USER->value));
            $newRoles[] = UserRole::MEMBER->value;
        }

        $user->setRoles(array_values(array_unique($newRoles)));
    }

    /**
     * Indique si l'utilisateur possède un rôle supérieur aux rôles club-level.
     */
    private function hasSuperiorRole(User $user): bool
    {
        $superiorRoles = [
            UserRole::EQUIPMENT_MANAGER_CTK->value,
            UserRole::EQUIPMENT_MANAGER_CN->value,
            UserRole::ADMIN->value,
        ];

        return [] !== array_intersect($user->getRoles(), $superiorRoles);
    }

    /**
     * Retire ROLE_USER du tableau (getRoles() l'ajoute automatiquement).
     *
     * @param string[] $roles
     *
     * @return string[]
     */
    private function stripBaseRoles(array $roles): array
    {
        return array_values(array_filter($roles, fn (string $r): bool => UserRole::USER->value !== $r));
    }
}
