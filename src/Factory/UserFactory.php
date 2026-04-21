<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use App\Enum\UserRole;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    /**
     * @return array<string, string|mixed[]>
     */
    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'password' => '$2y$13$hashed_password_placeholder', // not used for login in tests
            'roles' => [],
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
    }

    // -------------------------------------------------------------------------
    // Convenience named constructors — one per role
    // -------------------------------------------------------------------------

    public static function withRole(UserRole $userRole): self
    {
        return self::new(['roles' => [$userRole->value]]);
    }

    public static function asUser(): self
    {
        return self::new(['roles' => []]);
    }

    public static function asMember(): self
    {
        return self::new(['roles' => [UserRole::MEMBER->value]]);
    }

    public static function asClubPresident(): self
    {
        return self::new(['roles' => [UserRole::CLUB_PRESIDENT->value]]);
    }

    public static function asEquipmentManagerClub(): self
    {
        return self::new(['roles' => [UserRole::EQUIPMENT_MANAGER_CLUB->value]]);
    }

    public static function asEquipmentManagerCtk(): self
    {
        return self::new(['roles' => [UserRole::EQUIPMENT_MANAGER_CTK->value]]);
    }

    public static function asEquipmentManagerCn(): self
    {
        return self::new(['roles' => [UserRole::EQUIPMENT_MANAGER_CN->value]]);
    }

    public static function asAdmin(): self
    {
        return self::new(['roles' => [UserRole::ADMIN->value]]);
    }
}
