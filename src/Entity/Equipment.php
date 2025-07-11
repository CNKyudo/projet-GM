<?php

namespace App\Entity;

use App\Repository\EquipmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\InheritanceType;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: EquipmentRepository::class)]
#[InheritanceType('SINGLE_TABLE')]
#[DiscriminatorColumn(name: 'equipment_type', type: 'string')]
#[DiscriminatorMap([
    'yumi' => Yumi::class,
    'glove' => Glove::class
])]
class Equipment
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'equipments')]
    private ?Club $owner_club = null;

    #[ORM\ManyToOne(inversedBy: 'borrowed_equipments')]
    private ?Club $borrower_club = null;

    #[ORM\ManyToOne(inversedBy: 'borrowed_equipments')]
    private ?User $borrower_user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnerClub(): ?Club
    {
        return $this->owner_club;
    }

    public function setOwnerClub(?Club $owner_club): static
    {
        $this->owner_club = $owner_club;

        return $this;
    }

    public function getBorrowerClub(): ?Club
    {
        return $this->borrower_club;
    }

    public function setBorrowerClub(?Club $borrower_club): static
    {
        $this->borrower_club = $borrower_club;

        return $this;
    }

    public function getBorrowerUser(): ?User
    {
        return $this->borrower_user;
    }

    public function setBorrowerUser(?User $borrower_user): static
    {
        $this->borrower_user = $borrower_user;

        return $this;
    }
}
