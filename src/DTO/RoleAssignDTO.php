<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Club;
use App\Entity\Region;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * DTO utilisé pour le formulaire d'affectation de rôle.
 *
 * - newRole      : valeur du rôle cible (ex : 'ROLE_CLUB_PRESIDENT')
 * - club         : club cible pour ROLE_CLUB_PRESIDENT et ROLE_EQUIPMENT_MANAGER_CLUB
 * - managedRegions : régions gérées pour ROLE_EQUIPMENT_MANAGER_CTK
 */
final class RoleAssignDTO
{
    public ?string $newRole = null;

    public ?Club $club = null;

    /** @var Collection<int, Region> */
    public Collection $managedRegions;

    public function __construct()
    {
        $this->managedRegions = new ArrayCollection();
    }
}
