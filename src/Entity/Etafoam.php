<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Repository\EtafoamRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;

#[ORM\Entity(repositoryClass: EtafoamRepository::class)]
class Etafoam extends Equipment
{
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Versioned]
    private ?float $length = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Versioned]
    private ?float $width = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Versioned]
    private ?float $thickness = null;

    public static function getType(): EquipmentType
    {
        return EquipmentType::ETAFOAM;
    }

    public function getLength(): ?float
    {
        return $this->length;
    }

    public function setLength(?float $length): static
    {
        $this->length = $length;

        return $this;
    }

    public function getWidth(): ?float
    {
        return $this->width;
    }

    public function setWidth(?float $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getThickness(): ?float
    {
        return $this->thickness;
    }

    public function setThickness(?float $thickness): static
    {
        $this->thickness = $thickness;

        return $this;
    }
}
