<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte avec cet email existe déjà.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
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

    /**
     * @var Collection<int, Equipment>
     */
    #[ORM\OneToMany(targetEntity: Equipment::class, mappedBy: 'borrower_user')]
    private Collection $borrowed_equipments;

    public function __construct()
    {
        $this->borrowed_equipments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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
        $roles[] = 'ROLE_USER';

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

    public function getClubWhichImPresidentOf(): ?Club
    {
        return $this->clubWhichImPresidentOf;
    }

    public function setClubWhichImPresidentOf(Club $clubWhichImPresidentOf): static
    {
        // set the owning side of the relation if necessary
        if ($clubWhichImPresidentOf->getPresident() !== $this) {
            $clubWhichImPresidentOf->setPresident($this);
        }

        $this->clubWhichImPresidentOf = $clubWhichImPresidentOf;

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
            $borrowedEquipment->setBorrowerUser($this);
        }

        return $this;
    }

    public function removeBorrowedEquipment(Equipment $borrowedEquipment): static
    {
        if ($this->borrowed_equipments->removeElement($borrowedEquipment)) {
            // set the owning side to null (unless already changed)
            if ($borrowedEquipment->getBorrowerUser() === $this) {
                $borrowedEquipment->setBorrowerUser(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
