<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Club;
use App\Entity\Region;
use App\Entity\User;
use App\Enum\UserRole;

/**
 * Résout les paramètres de filtrage de la liste des équipements
 * en fonction du rôle de l'utilisateur connecté.
 *
 * Ces paramètres sont ensuite passés à {@see \App\Repository\EquipmentRepository::findBySearchStrategy()}
 * afin de restreindre les résultats à ce que l'utilisateur est autorisé à voir.
 */
final class EquipmentVisibilityFilterResolver
{
    /**
     * Calcule les paramètres de filtrage à appliquer sur la liste des équipements
     * en fonction du rôle de l'utilisateur.
     *
     * Règles (matrice des droits v2) :
     *  - MEMBER      : propres clubs (tous statuts) + CTK propre (dispo seulement) + pas de national
     *  - PRESIDENT   : propre club (tous) + même CTK clubs (dispo) + toutes CTK régions (dispo) + pas de national
     *  - MGR_CLUB    : propre club (tous) + même CTK clubs (dispo) + toutes CTK régions (dispo) + pas de national
     *  - MGR_CTK     : tous clubs sa CTK (tous) + autres clubs (dispo) + ses régions (tous) + autres régions (dispo) + pas de national
     *  - MGR_CN/ADMIN: tout (null → aucune restriction)
     *
     * @return array{
     *   restrictToClubs: list<Club>|null,
     *   allowedClubsAvailableOnly: list<Club>|null,
     *   allowedRegions: list<Region>,
     *   onlyAvailableRegional: bool,
     *   includeAllAvailableRegional: bool,
     *   includeNational: bool,
     * }
     */
    public function resolve(User $user): array
    {
        // MGR_CN et ADMIN : aucune restriction — null déclenche le court-circuit dans la stratégie
        if ($this->isCnOrAdmin($user)) {
            return [
                'restrictToClubs'             => null,
                'allowedClubsAvailableOnly'   => [],
                'allowedRegions'              => [],
                'onlyAvailableRegional'       => false,
                'includeAllAvailableRegional' => false,
                'includeNational'             => true,
            ];
        }

        // MGR_CTK : tous les clubs de ses régions gérées (tous statuts)
        //           + tous les autres clubs (disponibles seulement)
        //           + ses régions gérées (tous statuts)
        //           + toutes les autres régions (disponibles seulement)
        if ($this->hasAnyRole($user, UserRole::EQUIPMENT_MANAGER_CTK)) {
            $ownCtkClubs = [];
            foreach ($user->getManagedRegions() as $region) {
                foreach ($region->getClubs() as $club) {
                    $ownCtkClubs[] = $club;
                }
            }

            return [
                'restrictToClubs'             => $ownCtkClubs,
                'allowedClubsAvailableOnly'   => null, // null = tous les clubs, dispo seulement
                'allowedRegions'              => array_values($user->getManagedRegions()->toArray()),
                'onlyAvailableRegional'       => false,
                'includeAllAvailableRegional' => true,
                'includeNational'             => false,
            ];
        }

        // PRESIDENT et MGR_CLUB : propre club (tous statuts)
        //                         + clubs de même CTK (disponibles seulement)
        //                         + toutes régions (disponibles seulement)
        if ($this->isClubLevel($user)) {
            $ownClub = $user->getClubWhichImPresidentOf() ?? $user->getClubWhereImEquipmentManager();
            $sameCtkClubs = [];
            if ($ownClub instanceof Club) {
                $ownRegion = $ownClub->getRegion();
                if ($ownRegion instanceof Region) {
                    foreach ($ownRegion->getClubs() as $club) {
                        if ($club->getId() !== $ownClub->getId()) {
                            $sameCtkClubs[] = $club;
                        }
                    }
                }
            }

            return [
                'restrictToClubs'             => $ownClub instanceof Club ? [$ownClub] : [],
                'allowedClubsAvailableOnly'   => $sameCtkClubs,
                'allowedRegions'              => [],
                'onlyAvailableRegional'       => false,
                'includeAllAvailableRegional' => true,
                'includeNational'             => false,
            ];
        }

        // MEMBER : propres clubs (tous statuts) + propre CTK régionale (disponible seulement)
        $memberClubs = array_values($user->getMemberOfClubs()->toArray());
        $memberRegions = [];
        foreach ($memberClubs as $club) {
            $region = $club->getRegion();
            if ($region instanceof Region) {
                $memberRegions[$region->getId()] = $region;
            }
        }

        return [
            'restrictToClubs'             => $memberClubs,
            'allowedClubsAvailableOnly'   => [],
            'allowedRegions'              => array_values($memberRegions),
            'onlyAvailableRegional'       => true,
            'includeAllAvailableRegional' => false,
            'includeNational'             => false,
        ];
    }

    private function isCnOrAdmin(User $user): bool
    {
        return $this->hasAnyRole($user, ...RoleRegistry::cnOrAdmin());
    }

    private function isClubLevel(User $user): bool
    {
        return $this->hasAnyRole($user, ...RoleRegistry::clubLevel());
    }

    private function hasAnyRole(User $user, UserRole ...$requiredRoles): bool
    {
        $userRoles = $user->getRoles();

        return array_any($requiredRoles, fn (UserRole $requiredRole): bool => \in_array($requiredRole->value, $userRoles, true));
    }
}
