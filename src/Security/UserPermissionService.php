<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\UserRole;

final class UserPermissionService
{
    public function canAccessUserManagement(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::ADMIN);
    }

    public function canEditUserRoles(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::ADMIN);
    }

    public function canCreateNationalEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CN, UserRole::ADMIN);
    }

    public function canCreateRegionalEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::EQUIPMENT_MANAGER_CN, UserRole::ADMIN);
    }

    public function canCreateOwnClubEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CLUB, UserRole::ADMIN);
    }

    public function canCreateEquipmentForOtherClub(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::EQUIPMENT_MANAGER_CN, UserRole::ADMIN);
    }

    public function canEditNationalEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::EQUIPMENT_MANAGER_CN, UserRole::ADMIN);
    }

    public function canEditRegionalEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::EQUIPMENT_MANAGER_CN, UserRole::ADMIN);
    }

    public function canEditOwnClubEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CLUB, UserRole::ADMIN);
    }

    public function canEditEquipmentFromOtherClub(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::ADMIN);
    }

    public function canViewOwnClubEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::MEMBER, UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CLUB, UserRole::ADMIN);
    }

    public function canViewEquipmentFromOtherClub(User $user): bool
    {
      // @todo EQUIPMENT_MANAGER_CLUB can View equipement only if the the CTKyudo
        return $this->hasAnyRole($user, UserRole::MEMBER, UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CLUB, UserRole::ADMIN);
    }

    public function canCreateClub(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::EQUIPMENT_MANAGER_CN, UserRole::ADMIN);
    }

    public function canTransferClubPresidency(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::CLUB_PRESIDENT, UserRole::ADMIN);
    }

    public function canAppointClubPresident(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::EQUIPMENT_MANAGER_CN, UserRole::ADMIN);
    }

    public function canAssignRegionalRoles(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::ADMIN);
    }

    public function canAssignNationalRoles(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CN, UserRole::ADMIN);
    }

    public function canAssignUserToAnyClub(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::ADMIN);
    }

    public function canAssignUserToOwnClub(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::ADMIN);
    }

    public function canBorrowOwnClubEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::MEMBER, UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CLUB, UserRole::ADMIN);
    }

    public function canBorrowEquipmentFromOtherClub(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CLUB, UserRole::ADMIN);
    }

    public function canSetAnotherBorrowerForOwnClubEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CLUB, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::ADMIN);
    }

    public function canSetAnotherBorrowerForOtherClubEquipment(User $user): bool
    {
        return $this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK, UserRole::ADMIN);
    }

    public function canEditOwnAccountInformation(User $user): bool
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

    public function hasAtLeastRole(User $user, UserRole $minimumRole): bool
    {
        return $this->getHighestRoleLevel($user) >= $this->getRoleLevel($minimumRole);
    }

    private function getHighestRoleLevel(User $user): int
    {
        $highest = 0;

        foreach ($user->getRoles() as $role) {
            $highest = max($highest, $this->getRoleLevelFromString($role));
        }

        return $highest;
    }

    private function getRoleLevel(UserRole $role): int
    {
        return UserRole::levels()[$role->value] ?? 0;
    }

    private function getRoleLevelFromString(string $role): int
    {
        return UserRole::levels()[$role] ?? 0;
    }
}
