<?php

namespace App\Entity;

use App\Repository\ClubRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ClubRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Club
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\OneToOne(inversedBy: 'clubWhichImPresidentOf')]
    private ?User $president = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Address $address = null;

    /**
     * @var Collection<int, Equipment>
     */
    #[ORM\OneToMany(targetEntity: Equipment::class, mappedBy: 'owner_club')]
    private Collection $owned_equipments;

    /**
     * @var Collection<int, Equipment>
     */
    #[ORM\OneToMany(targetEntity: Equipment::class, mappedBy: 'borrower_club')]
    private Collection $borrowed_equipments;

    public function __construct()
    {
        $this->owned_equipments = new ArrayCollection();
        $this->borrowed_equipments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPresident(): ?User
    {
        return $this->president;
    }

    public function setPresident(User $president): static
    {
        $this->president = $president;

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

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): static
    {
        $this->address = $address;

        return $this;
    }

    /**
     * @return Collection<int, Equipment>
     */
    public function getOwnedEquipments(): Collection
    {
        return $this->owned_equipments;
    }

    public function addOwnedEquipment(Equipment $equipment): static
    {
        if (!$this->owned_equipments->contains($equipment)) {
            $this->owned_equipments->add($equipment);
            $equipment->setOwnerClub($this);
        }

        return $this;
    }

    public function removeOwnedEquipment(Equipment $equipment): static
    {
        if ($this->owned_equipments->removeElement($equipment)) {
            // set the owning side to null (unless already changed)
            if ($equipment->getOwnerClub() === $this) {
                $equipment->setOwnerClub(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Equipment>
     */
    public function getBorrowedEquipments(): Collection
    {
        return $this->borrowed_equipments;
    }

    public function addBorrowedEquipment(Equipment $borrowedEquipment): static
    {
        if (!$this->borrowed_equipments->contains($borrowedEquipment)) {
            $this->borrowed_equipments->add($borrowedEquipment);
            $borrowedEquipment->setBorrowerClub($this);
        }

        return $this;
    }

    public function removeBorrowedEquipment(Equipment $borrowedEquipment): static
    {
        if ($this->borrowed_equipments->removeElement($borrowedEquipment)) {
            // set the owning side to null (unless already changed)
            if ($borrowedEquipment->getBorrowerClub() === $this) {
                $borrowedEquipment->setBorrowerClub(null);
            }
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
