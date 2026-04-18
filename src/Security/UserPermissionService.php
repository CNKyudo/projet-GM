<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Region;
use App\Entity\User;
use App\Enum\UserRole;

final class UserPermissionService
{
    // -----------------------------------------------------------------------
    // Gestion des utilisateurs
    // -----------------------------------------------------------------------

    public function canAccessUserManagement(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::ADMIN);
    }

    public function canEditOwnAccountInformation(User $user): bool
    {
        return $this->hasAtLeastRole($user, UserRole::USER);
    }

    public function canAssignUserToAnyClub(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::ADMIN);
    }

    public function canAssignUserToOwnClub(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::ADMIN);
    }

    // -----------------------------------------------------------------------
    // Gestion des clubs
    // -----------------------------------------------------------------------

    public function canCreateClub(User $user): bool
    {
        return $this->isCtkOrAbove($user);
    }

    public function canTransferClubPresidency(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::CLUB_PRESIDENT, UserRole::ADMIN);
    }

    public function canEditClub(User $user): bool
    {
        return $this->isCtkOrAbove($user);
    }

    public function canDeleteClub(User $user): bool
    {
        return $this->isCnOrAdmin($user);
    }

    // -----------------------------------------------------------------------
    // Gestion des adresses
    // -----------------------------------------------------------------------

    public function canCreateAddress(User $user): bool
    {
        return $this->isCtkOrAbove($user);
    }

    public function canEditAddress(User $user): bool
    {
        return $this->isCtkOrAbove($user);
    }

    public function canDeleteAddress(User $user): bool
    {
        return $this->isCnOrAdmin($user);
    }

    // -----------------------------------------------------------------------
    // Gestion des équipements — création (sans contexte sujet)
    // -----------------------------------------------------------------------

    /**
     * Droit de parcourir la liste globale des équipements (index).
     */
    public function canBrowseAllEquipment(User $user): bool
    {
        return $this->isMemberOrAbove($user);
    }

    /**
     * Peut créer un équipement national (propriétaire = Fédération).
     */
    public function canCreateNationalEquipment(User $user): bool
    {
        return $this->isCnOrAdmin($user);
    }

    /**
     * Peut créer un équipement régional (propriétaire = Région).
     * Sans contexte sujet : vérifie uniquement le rôle.
     * Avec contexte : @see canCreateRegionalEquipmentForRegion().
     */
    public function canCreateRegionalEquipment(User $user): bool
    {
        return $this->isCtkOrAbove($user);
    }

    /**
     * Peut créer un équipement régional pour UNE région spécifique.
     * CTK : uniquement si la région fait partie de ses régions gérées.
     */
    public function canCreateRegionalEquipmentForRegion(User $user, Region $region): bool
    {
        if ($this->isCnOrAdmin($user)) {
            return true;
        }

        if ($this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK)) {
            return $user->getManagedRegions()->contains($region);
        }

        return false;
    }

    /**
     * Peut créer un équipement pour son propre club (propriétaire = Club).
     */
    public function canCreateOwnClubEquipment(User $user): bool
    {
        return $this->isClubLevel($user) || $this->hasAnyRole($user, UserRole::ADMIN);
    }

    /**
     * Peut créer un équipement pour un autre club (CTK/CN/ADMIN).
     */
    public function canCreateEquipmentForOtherClub(User $user): bool
    {
        return $this->isCtkOrAbove($user);
    }

    // -----------------------------------------------------------------------
    // Gestion des équipements — visualisation (avec sujet Equipment)
    // -----------------------------------------------------------------------

    /**
     * Peut voir un équipement de son propre club.
     */
    public function canViewOwnClubEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::MEMBER, UserRole::ADMIN) || $this->isClubLevel($user);
    }

    /**
     * Peut voir un équipement d'un autre club sans restriction de région.
     * MEMBER et PRESIDENT : accès à tout autre club.
     * MANAGER_CLUB : limité aux clubs de sa propre région → @see canViewOtherClubEquipmentInOwnRegion().
     */
    public function canViewEquipmentFromOtherClub(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::MEMBER, UserRole::CLUB_PRESIDENT, UserRole::ADMIN);
    }

    /**
     * MANAGER_CLUB peut voir les équipements CLUB d'un autre club,
     * à condition que ce club soit dans la même région que son propre club.
     */
    public function canViewOtherClubEquipmentInOwnRegion(User $user, Equipment $equipment): bool
    {
        if (!$this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CLUB)) {
            return false;
        }

        $managerClub = $user->getClubWhereImEquipmentManager();
        if (!$managerClub instanceof Club) {
            return false;
        }

        $managerRegion = $managerClub->getRegion();
        if (!$managerRegion instanceof Region) {
            return false;
        }

        $ownerClub = $equipment->getOwnerClub();
        if (!$ownerClub instanceof Club) {
            return false;
        }

        return $ownerClub->getRegion()?->getId() === $managerRegion->getId();
    }

    /**
     * Peut voir un équipement régional.
     * Tous les utilisateurs rattachés à un club peuvent voir les équipements
     * de leur région. CN/CTK/ADMIN voient tous les équipements régionaux.
     */
    public function canViewRegionalEquipment(User $user, Equipment $equipment): bool
    {
        if ($this->isCtkOrAbove($user)) {
            return true;
        }

        // MEMBER, PRESIDENT, MANAGER_CLUB : voient l'équipement régional de leur région
        if ($this->hasAnyRole($user, UserRole::MEMBER) || $this->isClubLevel($user)) {
            $ownerRegion = $equipment->getOwnerRegion();
            if (!$ownerRegion instanceof Region) {
                return false;
            }

            return $this->userBelongsToRegion($user, $ownerRegion);
        }

        return false;
    }

    /**
     * Peut voir un équipement national (tous les utilisateurs connectés).
     */
    public function canViewNationalEquipment(User $user): bool
    {
        return $this->hasAtLeastRole($user, UserRole::USER);
    }

    // -----------------------------------------------------------------------
    // Gestion des équipements — modification (avec sujet Equipment)
    // -----------------------------------------------------------------------

    /**
     * Peut modifier un équipement de son propre club.
     */
    public function canEditOwnClubEquipment(User $user): bool
    {
        return $this->isClubLevel($user) || $this->hasAnyRole($user, UserRole::ADMIN);
    }

    /**
     * Peut modifier un équipement d'un autre club.
     */
    public function canEditEquipmentFromOtherClub(User $user): bool
    {
        return $this->isCtkOrAbove($user);
    }

    /**
     * Peut modifier un équipement régional.
     * CTK : uniquement si la région est parmi ses régions gérées.
     */
    public function canEditRegionalEquipment(User $user, Equipment $equipment): bool
    {
        if ($this->isCnOrAdmin($user)) {
            return true;
        }

        if ($this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK)) {
            $ownerRegion = $equipment->getOwnerRegion();

            return $ownerRegion instanceof Region && $user->getManagedRegions()->contains($ownerRegion);
        }

        return false;
    }

    /**
     * Peut modifier un équipement national.
     * CTK peut modifier (mais pas créer) un équipement national.
     */
    public function canEditNationalEquipment(User $user): bool
    {
        return $this->isCtkOrAbove($user);
    }

    // -----------------------------------------------------------------------
    // Gestion des équipements — emprunts (avec sujet Equipment)
    // -----------------------------------------------------------------------

    public function canBorrowOwnClubEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::MEMBER, UserRole::ADMIN) || $this->isClubLevel($user);
    }

    public function canBorrowEquipmentFromOtherClub(User $user): bool
    {
        return $this->isClubLevel($user) || $this->hasAnyRole($user, UserRole::ADMIN);
    }

    /**
     * Peut emprunter un équipement régional ou national.
     * Aucune restriction de région pour l'emprunt.
     */
    public function canBorrowRegionalOrNationalEquipment(User $user): bool
    {
        return $this->isMemberOrAbove($user);
    }

    public function canSetAnotherBorrowerForOwnClubEquipment(User $user): bool
    {
        return $this->isClubLevel($user) || $this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::ADMIN);
    }

    public function canSetAnotherBorrowerForOtherClubEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::ADMIN);
    }

    // -----------------------------------------------------------------------
    // Helpers privés
    // -----------------------------------------------------------------------

    /**
     * Retourne true si l'utilisateur est rattaché à un club de la région donnée
     * (en tant que président, responsable matériel ou simple membre).
     */
    private function userBelongsToRegion(User $user, Region $region): bool
    {
        $presidentClub = $user->getClubWhichImPresidentOf();
        if ($presidentClub instanceof Club && $presidentClub->getRegion()?->getId() === $region->getId()) {
            return true;
        }

        $managerClub = $user->getClubWhereImEquipmentManager();
        if ($managerClub instanceof Club && $managerClub->getRegion()?->getId() === $region->getId()) {
            return true;
        }

        foreach ($user->getMemberOfClubs() as $memberOfClub) {
            if ($memberOfClub->getRegion()?->getId() === $region->getId()) {
                return true;
            }
        }

        return false;
    }

    // -----------------------------------------------------------------------
    // Combinaisons de rôles réutilisées (patterns nommés)
    // Les groupes sont définis dans RoleRegistry — source unique de vérité.
    // -----------------------------------------------------------------------

    /** CTK + CN + ADMIN — coordinateurs régionaux et au-dessus (9 usages). */
    private function isCtkOrAbove(User $user): bool
    {
        return $this->hasAnyRole($user, ...RoleRegistry::ctkOrAbove());
    }

    /** CN + ADMIN — coordinateurs nationaux et au-dessus (5 usages). */
    private function isCnOrAdmin(User $user): bool
    {
        return $this->hasAnyRole($user, ...RoleRegistry::cnOrAdmin());
    }

    /** PRESIDENT + MANAGER_CLUB — responsables de club (3 usages). */
    private function isClubLevel(User $user): bool
    {
        return $this->hasAnyRole($user, ...RoleRegistry::clubLevel());
    }

    /** MEMBER et au-dessus — tous les membres actifs (3 usages). */
    private function isMemberOrAbove(User $user): bool
    {
        return $this->hasAtLeastRole($user, RoleRegistry::memberMinimumRole());
    }

    public function canCreateQRCode(User $user): bool
    {
        return $this->hasAnyRole(
            $user,
            UserRole::CLUB_PRESIDENT,
            UserRole::EQUIPMENT_MANAGER_CLUB,
            UserRole::EQUIPMENT_MANAGER_CTK,
            UserRole::EQUIPMENT_MANAGER_CN,
            UserRole::ADMIN
        );
    }

    public function canEditQRCode(User $user): bool
    {
        return $this->hasAnyRole(
            $user,
            UserRole::CLUB_PRESIDENT,
            UserRole::EQUIPMENT_MANAGER_CLUB,
            UserRole::EQUIPMENT_MANAGER_CTK,
            UserRole::EQUIPMENT_MANAGER_CN,
            UserRole::ADMIN
        );
    }

    public function canDeleteQRCode(User $user): bool
    {
        return $this->hasAnyRole(
            $user,
            UserRole::EQUIPMENT_MANAGER_CTK,
            UserRole::EQUIPMENT_MANAGER_CN,
            UserRole::ADMIN
        );
    }

    public function canViewQRCode(User $user): bool
    {
        return $this->hasAtLeastRole($user, UserRole::USER);
    }

    private function hasAnyRole(User $user, UserRole ...$requiredRoles): bool
    {
        $userRoles = $user->getRoles();

        foreach ($requiredRoles as $requiredRole) {
            if (\in_array($requiredRole->value, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    private function hasAtLeastRole(User $user, UserRole $userRole): bool
    {
        return $this->getHighestRoleLevel($user) >= $this->getRoleLevel($userRole);
    }

    private function getHighestRoleLevel(User $user): int
    {
        $highest = 0;

        foreach ($user->getRoles() as $role) {
            $highest = max($highest, $this->getRoleLevelFromString($role));
        }

        return $highest;
    }

    private function getRoleLevel(UserRole $userRole): int
    {
        return UserRole::levels()[$userRole->value] ?? 0;
    }

    private function getRoleLevelFromString(string $role): int
    {
        return UserRole::levels()[$role] ?? 0;
    }
}
