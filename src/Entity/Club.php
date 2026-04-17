<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ClubRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ClubRepository::class)]
#[UniqueEntity(fields: ['president'], message: 'Cet utilisateur est déjà président d\'un club.', errorPath: 'president')]
#[UniqueEntity(fields: ['equipment_manager'], message: 'Cet utilisateur est déjà responsable matériel d\'un club.', errorPath: 'equipment_manager')]
class Club implements \Stringable
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\OneToOne(inversedBy: 'clubWhichImPresidentOf')]
    private ?User $president = null;

    #[ORM\OneToOne(inversedBy: 'clubWhereImEquipmentManager')]
    private ?User $equipment_manager = null;

    #[ORM\ManyToOne(inversedBy: 'clubs')]
    private ?Region $region = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Address $address = null;

    /**
     * Membres du club (hors présidence).
     *
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'memberOfClubs')]
    private Collection $members;

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
        $this->members = new ArrayCollection();
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

    public function setPresident(?User $user): static
    {
        $this->president = $user;

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
     * @return Collection<int, User>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $user): static
    {
        if (!$this->members->contains($user)) {
            $this->members->add($user);
            $user->addMemberOfClub($this);
        }

        return $this;
    }

    public function removeMember(User $user): static
    {
        if ($this->members->removeElement($user)) {
            $user->removeMemberOfClub($this);
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
        // set the owning side to null (unless already changed)
        if ($this->owned_equipments->removeElement($equipment) && $equipment->getOwnerClub() === $this) {
            $equipment->setOwnerClub(null);
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
        // set the owning side to null (unless already changed)
        if ($this->borrowed_equipments->removeElement($borrowedEquipment) && $borrowedEquipment->getBorrowerClub() === $this) {
            $borrowedEquipment->setBorrowerClub(null);
        }

        return $this;
    }

    public function getRegion(): ?Region
    {
        return $this->region;
    }

    public function setRegion(?Region $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getEquipmentManager(): ?User
    {
        return $this->equipment_manager;
    }

    public function setEquipmentManager(?User $user): static
    {
        // Éviter la récursion : on ne met à jour que si l'état a changé
        if ($this->equipment_manager === $user) {
            return $this;
        }

        $previous = $this->equipment_manager;
        $this->equipment_manager = $user;

        // Détacher l'ancien responsable
        if ($previous instanceof User && $previous->getClubWhereImEquipmentManager() === $this) {
            $previous->setClubWhereImEquipmentManager(null);
        }

        // Attacher le nouveau responsable
        if ($user instanceof User && $user->getClubWhereImEquipmentManager() !== $this) {
            $user->setClubWhereImEquipmentManager($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
