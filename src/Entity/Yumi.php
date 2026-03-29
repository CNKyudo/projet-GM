<?php

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Enum\YumiLength;
use App\Repository\YumiRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: YumiRepository::class)]
class Yumi extends Equipment
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $material = null;

    #[ORM\Column(nullable: true)]
    private ?int $strength = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?YumiLength $length = null;

    public static function getType(): EquipmentType
    {
        return EquipmentType::YUMI;
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

    public function getStrength(): ?int
    {
        return $this->strength;
    }

    public function setStrength(?int $strength): static
    {
        $this->strength = $strength;

        return $this;
    }

    public function getLength(): ?YumiLength
    {
        return $this->length;
    }

    public function setLength(?YumiLength $length): static
    {
        $this->length = $length;

        return $this;
    }
}
