<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

// Note: ClubMember est dans le même namespace App\Entity, pas besoin de use.

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte avec cet email existe déjà.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private string $password;

    #[ORM\OneToOne(mappedBy: 'president')]
    private ?Club $clubWhichImPresidentOf = null;

    #[ORM\OneToOne(mappedBy: 'equipmentManager')]
    private ?Club $clubWhereImEquipmentManager = null;

    /**
     * Clubs dont l'utilisateur est membre (hors présidence).
     *
     * @var Collection<int, Club>
     */
    #[ORM\ManyToMany(targetEntity: Club::class, inversedBy: 'members')]
    #[ORM\JoinTable(name: 'club_members')]
    private Collection $memberOfClubs;

    /**
     * Régions gérées par cet utilisateur (pour ROLE_EQUIPMENT_MANAGER_CTK).
     *
     * @var Collection<int, Region>
     */
    #[ORM\ManyToMany(targetEntity: Region::class, inversedBy: 'managers')]
    #[ORM\JoinTable(name: 'user_managed_regions')]
    private Collection $managedRegions;

    /**
     * Profil de membre de club (null si l'utilisateur n'est associé à aucun ClubMember).
     */
    #[ORM\OneToOne(mappedBy: 'user')]
    private ?ClubMember $clubMember = null;

    public function __construct()
    {
        $this->memberOfClubs = new ArrayCollection();
        $this->managedRegions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): string
    {
        return $this->clubMember?->getFullName() ?? $this->email;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        assert('' !== $this->email);

        return $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = UserRole::USER->value;

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection<int, Club>
     */
    public function getMemberOfClubs(): Collection
    {
        return $this->memberOfClubs;
    }

    public function addMemberOfClub(Club $club): static
    {
        if (!$this->memberOfClubs->contains($club)) {
            $this->memberOfClubs->add($club);
            $club->addMember($this);
        }

        return $this;
    }

    public function removeMemberOfClub(Club $club): static
    {
        if ($this->memberOfClubs->removeElement($club)) {
            $club->removeMember($this);
        }

        return $this;
    }

    public function getClubWhichImPresidentOf(): ?Club
    {
        return $this->clubWhichImPresidentOf;
    }

    public function setClubWhichImPresidentOf(?Club $club): static
    {
        if ($this->clubWhichImPresidentOf instanceof Club && $this->clubWhichImPresidentOf !== $club) {
            $this->clubWhichImPresidentOf->setPresident(null);
        }

        if ($club instanceof Club && $club->getPresident() !== $this) {
            $club->setPresident($this);
        }

        $this->clubWhichImPresidentOf = $club;

        return $this;
    }

    public function getClubWhereImEquipmentManager(): ?Club
    {
        return $this->clubWhereImEquipmentManager;
    }

    public function setClubWhereImEquipmentManager(?Club $club): static
    {
        // Éviter la récursion : on ne met à jour que si l'état a changé
        if ($this->clubWhereImEquipmentManager === $club) {
            return $this;
        }

        $previous = $this->clubWhereImEquipmentManager;
        $this->clubWhereImEquipmentManager = $club;

        // Détacher de l'ancien club
        if ($previous instanceof Club && $previous->getEquipmentManager() === $this) {
            $previous->setEquipmentManager(null);
        }

        // Attacher au nouveau club
        if ($club instanceof Club && $club->getEquipmentManager() !== $this) {
            $club->setEquipmentManager($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Region>
     */
    public function getManagedRegions(): Collection
    {
        return $this->managedRegions;
    }

    public function addManagedRegion(Region $region): static
    {
        if (!$this->managedRegions->contains($region)) {
            $this->managedRegions->add($region);
        }

        return $this;
    }

    public function removeManagedRegion(Region $region): static
    {
        $this->managedRegions->removeElement($region);

        return $this;
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }

    /**
     * Retourne les équipements empruntés via le ClubMember lié, ou une collection vide.
     *
     * @return Collection<int, Equipment>
     */
    public function getBorrowedEquipments(): Collection
    {
        return $this->clubMember?->getBorrowedEquipmentsMember() ?? new ArrayCollection();
    }

    public function getClubMember(): ?ClubMember
    {
        return $this->clubMember;
    }

    public function setClubMember(?ClubMember $clubMember): static
    {
        $this->clubMember = $clubMember;

        return $this;
    }
}
