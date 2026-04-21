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

    #[ORM\ManyToOne(inversedBy: 'ownedEquipments')]
    #[Versioned]
    private ?Club $ownerClub = null;

    /**
     * Propriétaire région (si equipment_level = REGIONAL).
     */
    #[ORM\ManyToOne(inversedBy: 'ownedEquipments')]
    #[Versioned]
    private ?Region $ownerRegion = null;

    /**
     * Propriétaire fédération (si equipment_level = NATIONAL).
     */
    #[ORM\ManyToOne(inversedBy: 'ownedEquipments')]
    #[Versioned]
    private ?Federation $ownerFederation = null;

    /**
     * Niveau de propriété : CLUB, REGIONAL ou NATIONAL.
     * Détermine quel champ owner* est non-null.
     */
    #[ORM\Column(length: 50, enumType: EquipmentLevel::class)]
    #[Versioned]
    private EquipmentLevel $equipmentLevel = EquipmentLevel::CLUB;

    #[ORM\ManyToOne(inversedBy: 'borrowedEquipmentsClub')]
    #[Versioned]
    private ?Club $borrowerClub = null;

    #[ORM\ManyToOne(inversedBy: 'borrowedEquipmentsMember')]
    #[Versioned]
    private ?ClubMember $borrowerMember = null;

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
        if (!$qrCode instanceof QRCode && $this->qrCode instanceof QRCode) {
            $this->qrCode->setEquipment(null);
        }

        if ($qrCode instanceof QRCode && $qrCode->getEquipment() !== $this) {
            $qrCode->setEquipment($this);
        }

        $this->qrCode = $qrCode;

        return $this;
    }

    public function getOwnerClub(): ?Club
    {
        return $this->ownerClub;
    }

    public function setOwnerClub(?Club $club): static
    {
        $this->ownerClub = $club;

        return $this;
    }

    public function getBorrowerClub(): ?Club
    {
        return $this->borrowerClub;
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
        return $this->ownerRegion;
    }

    public function setOwnerRegion(?Region $region): static
    {
        $this->ownerRegion = $region;

        return $this;
    }

    public function getOwnerFederation(): ?Federation
    {
        return $this->ownerFederation;
    }

    public function setOwnerFederation(?Federation $federation): static
    {
        $this->ownerFederation = $federation;

        return $this;
    }

    abstract public static function getType(): EquipmentType;

    public function getTypeName(): string
    {
        return static::getType()->value;
    }

    public function setBorrowerClub(?Club $club): static
    {
        $this->borrowerClub = $club;

        return $this;
    }

    public function getBorrowerMember(): ?ClubMember
    {
        return $this->borrowerMember;
    }

    public function setBorrowerMember(?ClubMember $member): static
    {
        $this->borrowerMember = $member;

        return $this;
    }
}
