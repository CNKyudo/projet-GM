<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Repository\SupportMakiwaraRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;

#[ORM\Entity(repositoryClass: SupportMakiwaraRepository::class)]
class SupportMakiwara extends Equipment
{
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Versioned]
    private ?float $hauteur = null;

    public static function getType(): EquipmentType
    {
        return EquipmentType::SUPPORT_MAKIWARA;
    }

    public function getHauteur(): ?float
    {
        return $this->hauteur;
    }

    public function setHauteur(?float $hauteur): static
    {
        $this->hauteur = $hauteur;

        return $this;
    }
}
