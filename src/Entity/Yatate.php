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
    private ?int $nb_fleches = null;

    public static function getType(): EquipmentType
    {
        return EquipmentType::YATATE;
    }

    public function getNbFleches(): ?int
    {
        return $this->nb_fleches;
    }

    public function setNbFleches(?int $nb_fleches): static
    {
        $this->nb_fleches = $nb_fleches;

        return $this;
    }
}
