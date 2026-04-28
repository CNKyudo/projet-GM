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
    private ?float $longueur = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Versioned]
    private ?float $hauteur = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Versioned]
    private ?string $material = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Versioned]
    private ?float $poids = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Versioned]
    private ?string $accroche = null;

    public static function getType(): EquipmentType
    {
        return EquipmentType::MAKU;
    }

    public function getLongueur(): ?float
    {
        return $this->longueur;
    }

    public function setLongueur(?float $longueur): static
    {
        $this->longueur = $longueur;

        return $this;
    }

    public function getHauteur(): ?float
    {
        return $this->hauteur;
    }

    public function setHauteur(?float $hauteur): static
    {
        $this->hauteur = $hauteur;

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

    public function getPoids(): ?float
    {
        return $this->poids;
    }

    public function setPoids(?float $poids): static
    {
        $this->poids = $poids;

        return $this;
    }

    public function getAccroche(): ?string
    {
        return $this->accroche;
    }

    public function setAccroche(?string $accroche): static
    {
        $this->accroche = $accroche;

        return $this;
    }
}
