<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case USER = 'ROLE_USER';
    case MEMBER = 'ROLE_MEMBER';
    case CLUB_PRESIDENT = 'ROLE_CLUB_PRESIDENT';
    case EQUIPMENT_MANAGER_CLUB = 'ROLE_EQUIPMENT_MANAGER_CLUB';
    case EQUIPMENT_MANAGER_CTK = 'ROLE_EQUIPMENT_MANAGER_CTK';
    case EQUIPMENT_MANAGER_CN = 'ROLE_EQUIPMENT_MANAGER_CN';
    case ADMIN = 'ROLE_ADMIN';

    /**
     * @return array<string, int>
     */
    public static function levels(): array
    {
        return [
            self::USER->value => 100,
            self::MEMBER->value => 200,
            self::CLUB_PRESIDENT->value => 300,
            self::EQUIPMENT_MANAGER_CLUB->value => 400,
            self::EQUIPMENT_MANAGER_CTK->value => 500,
            self::EQUIPMENT_MANAGER_CN->value => 600,
            self::ADMIN->value => 700,
        ];
    }

    /**
     * Convertit une liste de valeurs de rôle (strings) en leurs labels lisibles.
     *
     * @param list<string> $roles
     *
     * @return list<string>
     */
    public static function labelsFromStrings(array $roles): array
    {
        return array_map(
            static fn (string $role): string => self::tryFrom($role)?->label() ?? $role,
            $roles,
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::USER => 'Utilisateur',
            self::MEMBER => 'Membre',
            self::CLUB_PRESIDENT => 'Président de club',
            self::EQUIPMENT_MANAGER_CLUB => 'Responsable matériel club',
            self::EQUIPMENT_MANAGER_CTK => 'Responsable matériel CTK',
            self::EQUIPMENT_MANAGER_CN => 'Responsable matériel national',
            self::ADMIN => 'Administrateur',
        };
    }
}
