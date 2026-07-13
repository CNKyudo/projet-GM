<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Enum\YumiLength;
use App\Repository\TsuruRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;

#[ORM\Entity(repositoryClass: TsuruRepository::class)]
class Tsuru extends Equipment
{
    #[ORM\Column(nullable: true, enumType: YumiLength::class)]
    #[Versioned]
    private ?YumiLength $tsuru_length = null;

    #[ORM\Column(nullable: true)]
    #[Versioned]
    private ?float $strength_min = null;

    #[ORM\Column(nullable: true)]
    #[Versioned]
    private ?float $strength_max = null;

    #[ORM\Column]
    #[Versioned]
    private int $quantity = 1;

    public static function getType(): EquipmentType
    {
        return EquipmentType::TSURU;
    }

    public function getTsuruLength(): ?YumiLength
    {
        return $this->tsuru_length;
    }

    public function setTsuruLength(?YumiLength $tsuru_length): static
    {
        $this->tsuru_length = $tsuru_length;

        return $this;
    }

    public function getStrengthMin(): ?float
    {
        return $this->strength_min;
    }

    public function setStrengthMin(?float $strength_min): static
    {
        $this->strength_min = $strength_min;

        return $this;
    }

    public function getStrengthMax(): ?float
    {
        return $this->strength_max;
    }

    public function setStrengthMax(?float $strength_max): static
    {
        $this->strength_max = $strength_max;

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
