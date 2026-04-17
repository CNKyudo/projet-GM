<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\UserRole;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Expose les constantes de rôles dans tous les templates Twig.
 *
 * Usage dans un template :
 *   {% if is_granted(roles.ADMIN) %}
 *   {% if is_granted(roles.MEMBER) %}
 */
final class RoleExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @return array<string, array<string, string>>
     */
    public function getGlobals(): array
    {
        return [
            'roles' => [
                'USER'                   => UserRole::USER->value,
                'MEMBER'                 => UserRole::MEMBER->value,
                'CLUB_PRESIDENT'         => UserRole::CLUB_PRESIDENT->value,
                'EQUIPMENT_MANAGER_CLUB' => UserRole::EQUIPMENT_MANAGER_CLUB->value,
                'EQUIPMENT_MANAGER_CTK'  => UserRole::EQUIPMENT_MANAGER_CTK->value,
                'EQUIPMENT_MANAGER_CN'   => UserRole::EQUIPMENT_MANAGER_CN->value,
                'ADMIN'                  => UserRole::ADMIN->value,
            ],
        ];
    }
}
