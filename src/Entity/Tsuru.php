<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Enum\TsuruLength;
use App\Repository\TsuruRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;

#[ORM\Entity(repositoryClass: TsuruRepository::class)]
class Tsuru extends Equipment
{
    #[ORM\Column(nullable: true, enumType: TsuruLength::class)]
    #[Versioned]
    private ?TsuruLength $tsuruLength = null;

    #[ORM\Column(nullable: true)]
    #[Versioned]
    private ?float $strengthMin = null;

    #[ORM\Column(nullable: true)]
    #[Versioned]
    private ?float $strengthMax = null;

    #[ORM\Column]
    #[Versioned]
    private int $quantity = 1;

    public static function getType(): EquipmentType
    {
        return EquipmentType::TSURU;
    }

    public function getTsuruLength(): ?TsuruLength
    {
        return $this->tsuruLength;
    }

    public function setTsuruLength(?TsuruLength $tsuruLength): static
    {
        $this->tsuruLength = $tsuruLength;

        return $this;
    }

    public function getStrengthMin(): ?float
    {
        return $this->strengthMin;
    }

    public function setStrengthMin(?float $strengthMin): static
    {
        $this->strengthMin = $strengthMin;

        return $this;
    }

    public function getStrengthMax(): ?float
    {
        return $this->strengthMax;
    }

    public function setStrengthMax(?float $strengthMax): static
    {
        $this->strengthMax = $strengthMax;

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
