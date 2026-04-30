<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Enum\MakiwaraMaterial;
use App\Repository\MakiwaraRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;

#[ORM\Entity(repositoryClass: MakiwaraRepository::class)]
class Makiwara extends Equipment
{
    #[ORM\Column(length: 255, nullable: true, enumType: MakiwaraMaterial::class)]
    #[Versioned]
    private ?MakiwaraMaterial $material = null;

    public static function getType(): EquipmentType
    {
        return EquipmentType::MAKIWARA;
    }

    public function getMaterial(): ?MakiwaraMaterial
    {
        return $this->material;
    }

    public function setMaterial(?MakiwaraMaterial $material): static
    {
        $this->material = $material;

        return $this;
    }
}
