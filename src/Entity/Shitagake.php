<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Repository\ShitagakeRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;

#[ORM\Entity(repositoryClass: ShitagakeRepository::class)]
class Shitagake extends Equipment
{
    #[ORM\Column(nullable: true)]
    #[Versioned]
    private ?int $nb_fingers = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Versioned]
    private ?string $size = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Versioned]
    private ?string $material = null;

    #[ORM\Column]
    #[Versioned]
    private int $quantity = 1;

    public static function getType(): EquipmentType
    {
        return EquipmentType::SHITAGAKE;
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

    public function getMaterial(): ?string
    {
        return $this->material;
    }

    public function setMaterial(?string $material): static
    {
        $this->material = $material;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }
}
