<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Enum\MakiwaraMaterial;
use App\Repository\MakiwaraRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: MakiwaraRepository::class)]
class Makiwara extends Equipment
{
    #[ORM\Column(length: 255, nullable: true, enumType: MakiwaraMaterial::class)]
    #[Versioned]
    private ?MakiwaraMaterial $material = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Versioned]
    private ?float $diameter = null;

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

    public function getDiameter(): ?float
    {
        return $this->diameter;
    }

    public function setDiameter(?float $diameter): static
    {
        $this->diameter = $diameter;

        return $this;
    }
}
