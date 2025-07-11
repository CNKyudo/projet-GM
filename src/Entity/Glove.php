<?php

namespace App\Entity;

use App\Repository\GloveRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GloveRepository::class)]
class Glove extends Equipment
{
    #[ORM\Column(nullable: true)]
    private ?int $nb_fingers = null;

    public function getNbFingers(): ?int
    {
        return $this->nb_fingers;
    }

    public function setNbFingers(?int $nb_fingers): static
    {
        $this->nb_fingers = $nb_fingers;

        return $this;
    }
}
