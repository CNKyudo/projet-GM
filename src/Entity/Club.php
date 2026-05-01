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
#[UniqueEntity(fields: ['equipmentManager'], message: 'Cet utilisateur est déjà responsable matériel d\'un club.', errorPath: 'equipmentManager')]
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
    private ?User $equipmentManager = null;

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
    #[ORM\OneToMany(targetEntity: Equipment::class, mappedBy: 'ownerClub')]
    private Collection $ownedEquipments;

    /**
     * @var Collection<int, Equipment>
     */
    #[ORM\OneToMany(targetEntity: Equipment::class, mappedBy: 'borrowerClub')]
    private Collection $borrowedEquipmentsClub;

    /**
     * Membres non-inscrits (et inscrits) rattachés au club.
     *
     * @var Collection<int, ClubMember>
     */
    #[ORM\OneToMany(targetEntity: ClubMember::class, mappedBy: 'club', cascade: ['persist', 'remove'])]
    private Collection $clubMembers;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->ownedEquipments = new ArrayCollection();
        $this->borrowedEquipmentsClub = new ArrayCollection();
        $this->clubMembers = new ArrayCollection();
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
        // Éviter la récursion : on ne met à jour que si l'état a changé
        if ($this->president === $user) {
            return $this;
        }

        $previous        = $this->president;
        $this->president = $user; // Affectation en premier pour couper la récursion

        // Détacher l'ancien président côté User
        if ($previous instanceof User && $previous->getClubWhichImPresidentOf() === $this) {
            $previous->setClubWhichImPresidentOf(null);
        }

        // Attacher le nouveau président côté User
        if ($user instanceof User && $user->getClubWhichImPresidentOf() !== $this) {
            $user->setClubWhichImPresidentOf($this);
        }

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
        return $this->ownedEquipments;
    }

    public function addOwnedEquipment(Equipment $equipment): static
    {
        if (!$this->ownedEquipments->contains($equipment)) {
            $this->ownedEquipments->add($equipment);
            $equipment->setOwnerClub($this);
        }

        return $this;
    }

    public function removeOwnedEquipment(Equipment $equipment): static
    {
        // set the owning side to null (unless already changed)
        if ($this->ownedEquipments->removeElement($equipment) && $equipment->getOwnerClub() === $this) {
            $equipment->setOwnerClub(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Equipment>
     */
    public function getBorrowedEquipmentsClub(): Collection
    {
        return $this->borrowedEquipmentsClub;
    }

    public function addBorrowedEquipment(Equipment $borrowedEquipment): static
    {
        if (!$this->borrowedEquipmentsClub->contains($borrowedEquipment)) {
            $this->borrowedEquipmentsClub->add($borrowedEquipment);
            $borrowedEquipment->setBorrowerClub($this);
        }

        return $this;
    }

    public function removeBorrowedEquipment(Equipment $borrowedEquipment): static
    {
        // set the owning side to null (unless already changed)
        if ($this->borrowedEquipmentsClub->removeElement($borrowedEquipment) && $borrowedEquipment->getBorrowerClub() === $this) {
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
        return $this->equipmentManager;
    }

    public function setEquipmentManager(?User $user): static
    {
        // Éviter la récursion : on ne met à jour que si l'état a changé
        if ($this->equipmentManager === $user) {
            return $this;
        }

        $previous                = $this->equipmentManager;
        $this->equipmentManager  = $user; // Affectation en premier pour couper la récursion

        // Détacher l'ancien gestionnaire côté User
        if ($previous instanceof User && $previous->getClubWhereImEquipmentManager() === $this) {
            $previous->setClubWhereImEquipmentManager(null);
        }

        // Attacher le nouveau gestionnaire côté User
        if ($user instanceof User && $user->getClubWhereImEquipmentManager() !== $this) {
            $user->setClubWhereImEquipmentManager($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @return Collection<int, ClubMember>
     */
    public function getClubMembers(): Collection
    {
        return $this->clubMembers;
    }

    public function addClubMember(ClubMember $clubMember): static
    {
        if (!$this->clubMembers->contains($clubMember)) {
            $this->clubMembers->add($clubMember);
            $clubMember->setClub($this);
        }

        return $this;
    }

    public function removeClubMember(ClubMember $clubMember): static
    {
        $this->clubMembers->removeElement($clubMember);

        return $this;
    }
}
