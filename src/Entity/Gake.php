<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Repository\GakeRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;

#[ORM\Entity(repositoryClass: GakeRepository::class)]
class Gake extends Equipment
{
    #[ORM\Column(nullable: true)]
    #[Versioned]
    private ?int $nb_fingers = null;

    #[ORM\Column(nullable: true)]
    #[Versioned]
    private ?string $size = null;

    public static function getType(): EquipmentType
    {
        return EquipmentType::GAKE;
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

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(?string $size): static
    {
        $this->size = $size;

        return $this;
    }
}
