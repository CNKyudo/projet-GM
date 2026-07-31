<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Equipment;
use App\Service\Equipment\EquipmentFieldProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose les champs spécifiques à chaque type d'équipement
 * afin de centraliser la logique d'affichage.
 */
class EquipmentExtension extends AbstractExtension
{
    public function __construct(
        private readonly EquipmentFieldProvider $fieldProvider,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('equipment_fields', $this->equipmentFields(...)),
        ];
    }

    /**
     * @return array<int, array{label: string, value: mixed}>
     */
    public function equipmentFields(Equipment $equipment): array
    {
        return $this->fieldProvider->getFields($equipment);
    }
}
