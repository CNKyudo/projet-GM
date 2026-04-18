<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FederationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: FederationRepository::class)]
class Federation implements \Stringable
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    /**
     * @var Collection<int, Region>
     */
    #[ORM\OneToMany(targetEntity: Region::class, mappedBy: 'federation')]
    private Collection $regions;

    /**
     * Équipements appartenant à la fédération (niveau national).
     *
     * @var Collection<int, Equipment>
     */
    #[ORM\OneToMany(targetEntity: Equipment::class, mappedBy: 'owner_federation')]
    private Collection $owned_equipments;

    public function __construct()
    {
        $this->regions = new ArrayCollection();
        $this->owned_equipments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return Collection<int, Region>
     */
    public function getRegions(): Collection
    {
        return $this->regions;
    }

    public function addRegion(Region $region): static
    {
        if (!$this->regions->contains($region)) {
            $this->regions->add($region);
            $region->setFederation($this);
        }

        return $this;
    }

    public function removeRegion(Region $region): static
    {
        if ($this->regions->removeElement($region) && $region->getFederation() === $this) {
            // La région ne peut pas exister sans fédération — on ne met pas null

        }

        return $this;
    }

    /**
     * @return Collection<int, Equipment>
     */
    public function getOwnedEquipments(): Collection
    {
        return $this->owned_equipments;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
