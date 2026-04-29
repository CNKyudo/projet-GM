<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentType;
use App\Repository\YatateRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Versioned;

#[ORM\Entity(repositoryClass: YatateRepository::class)]
class Yatate extends Equipment
{
    #[ORM\Column(nullable: true)]
    #[Versioned]
    private ?int $nb_arrows = null;

    public static function getType(): EquipmentType
    {
        return EquipmentType::YATATE;
    }

    public function getNbArrows(): ?int
    {
        return $this->nb_arrows;
    }

    public function setNbArrows(?int $nb_arrows): static
    {
        $this->nb_arrows = $nb_arrows;

        return $this;
    }
}
