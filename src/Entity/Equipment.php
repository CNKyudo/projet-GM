<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentLevel;
use App\Enum\EquipmentType;
use App\Repository\EquipmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\InheritanceType;
use Gedmo\Mapping\Annotation\Loggable;
use Gedmo\Mapping\Annotation\Versioned;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: EquipmentRepository::class)]
#[InheritanceType('SINGLE_TABLE')]
#[DiscriminatorColumn(name: 'equipment_type', type: 'string')]
#[DiscriminatorMap([
    'yumi' => Yumi::class,
    'glove' => Glove::class,
])]
#[Loggable]

abstract class Equipment
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: QRCode::class, mappedBy: 'equipment', cascade: ['persist', 'remove'])]
    private ?QRCode $qrCode = null;

    #[ORM\ManyToOne(inversedBy: 'owned_equipments')]
    #[Versioned]
    private ?Club $owner_club = null;

    /**
     * Propriétaire région (si equipment_level = REGIONAL).
     */
    #[ORM\ManyToOne(inversedBy: 'owned_equipments')]
    #[Versioned]
    private ?Region $owner_region = null;

    /**
     * Propriétaire fédération (si equipment_level = NATIONAL).
     */
    #[ORM\ManyToOne(inversedBy: 'owned_equipments')]
    #[Versioned]
    private ?Federation $owner_federation = null;

    /**
     * Niveau de propriété : CLUB, REGIONAL ou NATIONAL.
     * Détermine quel champ owner_* est non-null.
     */
    #[ORM\Column(length: 50, enumType: EquipmentLevel::class)]
    #[Versioned]
    private EquipmentLevel $equipmentLevel = EquipmentLevel::CLUB;

    #[ORM\ManyToOne(inversedBy: 'borrowed_equipments')]
    #[Versioned]
    private ?Club $borrower_club = null;

    #[ORM\ManyToOne(inversedBy: 'borrowed_equipments')]
    #[Versioned]
    private ?User $borrower_user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQrCode(): ?QRCode
    {
        return $this->qrCode;
    }

    public function setQrCode(?QRCode $qrCode): static
    {
        if (null === $qrCode && null !== $this->qrCode) {
            $this->qrCode->setEquipment(null);
        }

        if (null !== $qrCode && $qrCode->getEquipment() !== $this) {
            $qrCode->setEquipment($this);
        }

        $this->qrCode = $qrCode;

        return $this;
    }

    public function getOwnerClub(): ?Club
    {
        return $this->owner_club;
    }

    public function setOwnerClub(?Club $club): static
    {
        $this->owner_club = $club;

        return $this;
    }

    public function getBorrowerClub(): ?Club
    {
        return $this->borrower_club;
    }

    public function getEquipmentLevel(): EquipmentLevel
    {
        return $this->equipmentLevel;
    }

    public function setEquipmentLevel(EquipmentLevel $equipmentLevel): static
    {
        $this->equipmentLevel = $equipmentLevel;

        return $this;
    }

    public function getOwnerRegion(): ?Region
    {
        return $this->owner_region;
    }

    public function setOwnerRegion(?Region $region): static
    {
        $this->owner_region = $region;

        return $this;
    }

    public function getOwnerFederation(): ?Federation
    {
        return $this->owner_federation;
    }

    public function setOwnerFederation(?Federation $federation): static
    {
        $this->owner_federation = $federation;

        return $this;
    }

    abstract public static function getType(): EquipmentType;

    public function getTypeName(): string
    {
        return static::getType()->value;
    }

    public function setBorrowerClub(?Club $club): static
    {
        $this->borrower_club = $club;

        return $this;
    }

    public function getBorrowerUser(): ?User
    {
        return $this->borrower_user;
    }

    public function setBorrowerUser(?User $user): static
    {
        $this->borrower_user = $user;

        return $this;
    }
}
