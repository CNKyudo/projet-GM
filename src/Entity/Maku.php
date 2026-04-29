<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Repository\MakuRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;

#[ORM\Entity(repositoryClass: MakuRepository::class)]
class Maku extends Equipment
{
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Versioned]
    private ?float $length = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Versioned]
    private ?float $height = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Versioned]
    private ?string $material = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Versioned]
    private ?float $weight = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Versioned]
    private ?string $attachment = null;

    public static function getType(): EquipmentType
    {
        return EquipmentType::MAKU;
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

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function setHeight(?float $height): static
    {
        $this->height = $height;

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

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function getAttachment(): ?string
    {
        return $this->attachment;
    }

    public function setAttachment(?string $attachment): static
    {
        $this->attachment = $attachment;

        return $this;
    }
}
