<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RegionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: RegionRepository::class)]
class Region implements \Stringable
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'regions')]
    #[ORM\JoinColumn(nullable: false)]
    private Federation $federation;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    /**
     * Clubs appartenant à cette région.
     *
     * @var Collection<int, Club>
     */
    #[ORM\OneToMany(targetEntity: Club::class, mappedBy: 'region')]
    private Collection $clubs;

    /**
     * Responsables matériel CTK assignés à cette région.
     *
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'managedRegions')]
    private Collection $managers;

    /**
     * Équipements appartenant à cette région (niveau régional).
     *
     * @var Collection<int, Equipment>
     */
    #[ORM\OneToMany(targetEntity: Equipment::class, mappedBy: 'owner_region')]
    private Collection $owned_equipments;

    public function __construct()
    {
        $this->clubs = new ArrayCollection();
        $this->managers = new ArrayCollection();
        $this->owned_equipments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFederation(): Federation
    {
        return $this->federation;
    }

    public function setFederation(Federation $federation): static
    {
        $this->federation = $federation;

        return $this;
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
     * @return Collection<int, Club>
     */
    public function getClubs(): Collection
    {
        return $this->clubs;
    }

    public function addClub(Club $club): static
    {
        if (!$this->clubs->contains($club)) {
            $this->clubs->add($club);
            $club->setRegion($this);
        }

        return $this;
    }

    public function removeClub(Club $club): static
    {
        if ($this->clubs->removeElement($club) && $club->getRegion() === $this) {
            $club->setRegion(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getManagers(): Collection
    {
        return $this->managers;
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
