<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ClubMemberRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ClubMemberRepository::class)]
#[ORM\Table(name: 'club_member')]
class ClubMember implements \Stringable
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    /**
     * Lien optionnel vers un User inscrit (null si le membre n'a pas de compte).
     * Quand le User est supprimé, user_id est mis à NULL (ON DELETE SET NULL).
     */
    #[ORM\OneToOne(inversedBy: 'clubMember')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'clubMembers')]
    #[ORM\JoinColumn(nullable: false)]
    private Club $club;

    /**
     * @var Collection<int, Equipment>
     */
    #[ORM\OneToMany(targetEntity: Equipment::class, mappedBy: 'borrowerMember')]
    private Collection $borrowedEquipmentsMember;

    public function __construct()
    {
        $this->borrowedEquipmentsMember = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName.' '.$this->lastName;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        // Gestion de la relation inverse
        $previousUser = $this->user;
        $this->user = $user;

        if ($previousUser instanceof User && $previousUser->getClubMember() === $this) {
            $previousUser->setClubMember(null);
        }

        if ($user instanceof User && $user->getClubMember() !== $this) {
            $user->setClubMember($this);
        }

        return $this;
    }

    public function getClub(): Club
    {
        return $this->club;
    }

    public function setClub(Club $club): static
    {
        $this->club = $club;

        return $this;
    }

    /**
     * @return Collection<int, Equipment>
     */
    public function getBorrowedEquipmentsMember(): Collection
    {
        return $this->borrowedEquipmentsMember;
    }

    public function addBorrowedEquipment(Equipment $equipment): static
    {
        if (!$this->borrowedEquipmentsMember->contains($equipment)) {
            $this->borrowedEquipmentsMember->add($equipment);
            $equipment->setBorrowerMember($this);
        }

        return $this;
    }

    public function removeBorrowedEquipment(Equipment $equipment): static
    {
        if ($this->borrowedEquipmentsMember->removeElement($equipment) && $equipment->getBorrowerMember() === $this) {
            $equipment->setBorrowerMember(null);
        }

        return $this;
    }

    /**
     * Vérifie si ce membre peut être supprimé
     * (impossible s'il a du matériel emprunté).
     */
    public function canBeDeleted(): bool
    {
        return $this->borrowedEquipmentsMember->isEmpty();
    }

    public function isRegistered(): bool
    {
        return $this->user instanceof User;
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
