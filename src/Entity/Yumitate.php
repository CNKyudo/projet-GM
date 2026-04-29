<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Enum\YumitateOrientation;
use App\Repository\YumitateRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;

#[ORM\Entity(repositoryClass: YumitateRepository::class)]
class Yumitate extends Equipment
{
    #[ORM\Column(nullable: true)]
    #[Versioned]
    private ?int $nb_bows = null;

    #[ORM\Column(length: 255, nullable: true, enumType: YumitateOrientation::class)]
    #[Versioned]
    private ?YumitateOrientation $orientation = null;

    public static function getType(): EquipmentType
    {
        return EquipmentType::YUMITATE;
    }

    public function getNbBows(): ?int
    {
        return $this->nb_bows;
    }

    public function setNbBows(?int $nb_bows): static
    {
        $this->nb_bows = $nb_bows;

        return $this;
    }

    public function getOrientation(): ?YumitateOrientation
    {
        return $this->orientation;
    }

    public function setOrientation(?YumitateOrientation $orientation): static
    {
        $this->orientation = $orientation;

        return $this;
    }
}
