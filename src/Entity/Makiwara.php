<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Repository\MakiwaraRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;

#[ORM\Entity(repositoryClass: MakiwaraRepository::class)]
class Makiwara extends Equipment
{
    #[ORM\Column(length: 255, nullable: true)]
    #[Versioned]
    private ?string $material = null;

    public static function getType(): EquipmentType
    {
        return EquipmentType::MAKIWARA;
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
}
