<?php

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Repository\GloveRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;

#[ORM\Entity(repositoryClass: GloveRepository::class)]
class Glove extends Equipment
{
    #[ORM\Column(nullable: true)]
    #[Versioned]
    private ?int $nb_fingers = null;

    public static function getType(): EquipmentType
    {
        return EquipmentType::GLOVE;
    }

    public function getNbFingers(): ?int
    {
        return $this->nb_fingers;
    }

    public function setNbFingers(?int $nb_fingers): static
    {
        $this->nb_fingers = $nb_fingers;

        return $this;
    }
}
