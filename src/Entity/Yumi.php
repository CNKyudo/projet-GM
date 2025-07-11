<?php

namespace App\Entity;

use App\Repository\YumiRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: YumiRepository::class)]
class Yumi extends Equipment
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $material = null;

    public function getMaterial(): ?string
    {
        return $this->material;
    }

    public function setMaterial(?string $material): static
    {
        $this->material = $material;

        return $this;
    }
}
