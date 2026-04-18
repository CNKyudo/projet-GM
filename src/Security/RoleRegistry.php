<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\UserRole;

/**
 * RoleRegistry — source unique de vérité pour la stratégie de rôles et de permissions.
 *
 * Ce fichier répond aux questions :
 *   - Quels rôles existent et dans quel ordre hiérarchique ?
 *   - Quels groupes de rôles utilise-t-on fréquemment ?
 *   - Qui peut faire quoi dans les grandes lignes ?
 *
 * ┌───────────────────────────────────────────────────────────────────┐
 * │  Hiérarchie des rôles (du plus bas au plus haut)                  │
 * ├─────────────────────────────┬───────────┬─────────────────────────┤
 * │  Rôle                       │  Niveau   │  Label                  │
 * ├─────────────────────────────┼───────────┼─────────────────────────┤
 * │  ROLE_USER                  │  100      │  Utilisateur            │
 * │  ROLE_MEMBER                │  200      │  Membre                 │
 * │  ROLE_CLUB_PRESIDENT        │  300      │  Président de club      │
 * │  ROLE_EQUIPMENT_MANAGER_CLUB│  400      │  Resp. matériel club    │
 * │  ROLE_EQUIPMENT_MANAGER_CTK │  500      │  Resp. matériel CTK     │
 * │  ROLE_EQUIPMENT_MANAGER_CN  │  600      │  Resp. matériel national│
 * │  ROLE_ADMIN                 │  700      │  Administrateur         │
 * └─────────────────────────────┴───────────┴─────────────────────────┘
 *
 * ┌───────────────────────────────────────────────────────────────────┐
 * │  Groupes nommés (utilisés dans UserPermissionService)             │
 * ├──────────────────────────┬────────────────────────────────────────┤
 * │  Groupe                  │  Membres                               │
 * ├──────────────────────────┼────────────────────────────────────────┤
 * │  CLUB_LEVEL              │  CLUB_PRESIDENT + EQUIPMENT_MANAGER_CLUB│
 * │  CTK_OR_ABOVE            │  CTK + CN + ADMIN                      │
 * │  CN_OR_ADMIN             │  CN + ADMIN                            │
 * │  MEMBER_OR_ABOVE         │  MEMBER et tous les rôles supérieurs   │
 * └──────────────────────────┴────────────────────────────────────────┘
 *
 * Pour modifier un rôle ou un groupe :
 *   1. Mettre à jour l'enum UserRole        → src/Enum/UserRole.php
 *   2. Mettre à jour les groupes ici      → src/Security/RoleRegistry.php
 *   3. Mettre à jour ce fichier (tableau de bord)
 *   4. Mettre à jour UserPermissionService  → src/Security/UserPermissionService.php
 *   5. Lancer les tests : make test-functional
 */
final class RoleRegistry
{
    // -----------------------------------------------------------------------
    // Liste ordonnée des rôles (référence complète)
    // -----------------------------------------------------------------------

    /**
     * Retourne tous les rôles dans l'ordre hiérarchique croissant.
     *
     * @return UserRole[]
     */
    public static function allRoles(): array
    {
        return [
            UserRole::USER,
            UserRole::MEMBER,
            UserRole::CLUB_PRESIDENT,
            UserRole::EQUIPMENT_MANAGER_CLUB,
            UserRole::EQUIPMENT_MANAGER_CTK,
            UserRole::EQUIPMENT_MANAGER_CN,
            UserRole::ADMIN,
        ];
    }

    // -----------------------------------------------------------------------
    // Groupes nommés — utilisés dans UserPermissionService
    // -----------------------------------------------------------------------

    /**
     * Responsables de club : CLUB_PRESIDENT et EQUIPMENT_MANAGER_CLUB.
     * Droits sur leur propre club uniquement.
     *
     * @return UserRole[]
     */
    public static function clubLevel(): array
    {
        return [UserRole::CLUB_PRESIDENT, UserRole::EQUIPMENT_MANAGER_CLUB];
    }

    /**
     * Coordinateurs régionaux et au-dessus : CTK + CN + ADMIN.
     * Droits étendus sur plusieurs clubs/régions.
     *
     * @return UserRole[]
     */
    public static function ctkOrAbove(): array
    {
        return [UserRole::EQUIPMENT_MANAGER_CTK, UserRole::EQUIPMENT_MANAGER_CN, UserRole::ADMIN];
    }

    /**
     * Coordinateurs nationaux et au-dessus : CN + ADMIN.
     * Droits sur toute la fédération.
     *
     * @return UserRole[]
     */
    public static function cnOrAdmin(): array
    {
        return [UserRole::EQUIPMENT_MANAGER_CN, UserRole::ADMIN];
    }

    /**
     * Membres actifs et au-dessus : MEMBER et tous les rôles supérieurs.
     * Niveau minimum pour accéder aux équipements.
     */
    public static function memberMinimumRole(): UserRole
    {
        return UserRole::MEMBER;
    }

    // Empêcher l'instanciation
    private function __construct()
    {
    }
}
