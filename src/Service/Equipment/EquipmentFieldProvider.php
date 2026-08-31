<?php

declare(strict_types=1);

namespace App\Service\Equipment;

use App\Entity\Azuchi;
use App\Entity\Makiwara;
use App\Entity\Maku;
use App\Entity\Muneate;
use App\Entity\Shitagake;
use App\Entity\SupportMakiwara;
use App\Entity\Tsuru;
use App\Entity\Yatate;
use App\Entity\Yugake;
use App\Entity\Yumi;
use App\Entity\Yumitate;
use App\Entity\Equipment;

class EquipmentFieldProvider
{
    /**
     * @return array<int, array{label: string, value: mixed}>
     */
    public function getFields(Equipment $equipment): array
    {
        return match (true) {
            $equipment instanceof Azuchi => [
                [
                    'label' => 'Longueur (cm)',
                    'value' => $equipment->getEquipmentLength(),
                ],
                [
                    'label' => 'Largeur (cm)',
                    'value' => $equipment->getWidth(),
                ],
                [
                    'label' => 'Épaisseur (cm)',
                    'value' => $equipment->getThickness(),
                ],
            ],

            $equipment instanceof Yugake => [
                [
                    'label' => 'Doigts',
                    'value' => $equipment->getNbFingers(),
                ],
                [
                    'label' => 'Taille',
                    'value' => $equipment->getSize(),
                ],
            ],

            $equipment instanceof Makiwara => [
                [
                    'label' => 'Matériau',
                    'value' => $equipment->getMaterial(),
                ],
                [
                    'label' => 'Diamètre',
                    'value' => $equipment->getDiameter(),
                ],
            ],

            $equipment instanceof Maku => [
                [
                    'label' => 'Longueur (m)',
                    'value' => $equipment->getEquipmentLength(),
                ],
                [
                    'label' => 'Hauteur (m)',
                    'value' => $equipment->getHeight(),
                ],
                [
                    'label' => 'Matériau',
                    'value' => $equipment->getMaterial(),
                ],
                [
                    'label' => 'Poids (kg)',
                    'value' => $equipment->getWeight(),
                ],
                [
                    'label' => 'Accroche',
                    'value' => $equipment->getAttachment(),
                ],
            ],

            $equipment instanceof Muneate => [
                [
                    'label' => 'Taille',
                    'value' => $equipment->getSize(),
                ],
                [
                    'label' => 'Matériau',
                    'value' => $equipment->getMaterial(),
                ],
                [
                    'label' => 'Quantité',
                    'value' => $equipment->getQuantity(),
                ],
            ],

            $equipment instanceof Shitagake => [
                [
                    'label' => 'Doigts',
                    'value' => $equipment->getNbFingers(),
                ],
                [
                    'label' => 'Taille',
                    'value' => $equipment->getSize(),
                ],
                [
                    'label' => 'Matériau',
                    'value' => $equipment->getMaterial(),
                ],
                [
                    'label' => 'Quantité',
                    'value' => $equipment->getQuantity(),
                ],
            ],

            $equipment instanceof SupportMakiwara => [
                [
                    'label' => 'Hauteur (m)',
                    'value' => $equipment->getHeight(),
                ],
            ],

            $equipment instanceof Tsuru => [
                [
                    'label' => 'Longueur',
                    'value' => $equipment->getTsuruLength()?->value,
                ],
                [
                    'label' => 'Force',
                    'value' => $equipment->getStrengthMin() ? $equipment->getStrengthMin().' kg' : null,
                ],
                [
                    'label' => 'Force',
                    'value' => $equipment->getStrengthMax() ? $equipment->getStrengthMax().' kg' : null,
                ],
                [
                    'label' => 'Quantité',
                    'value' => $equipment->getQuantity(),
                ],

            ],

            $equipment instanceof Yatate => [
                [
                    'label' => 'Nombre de flèches',
                    'value' => $equipment->getNbArrows(),
                ],
            ],

            $equipment instanceof Yumi => [
                [
                    'label' => 'Matériau',
                    'value' => $equipment->getMaterial(),
                ],
                [
                    'label' => 'Force',
                    'value' => $equipment->getStrength() ? $equipment->getStrength().' kg' : null,
                ],
                [
                    'label' => 'Longueur',
                    'value' => $equipment->getYumiLength()?->value,
                ],
            ],

            $equipment instanceof Yumitate => [
                [
                    'label' => "Nombre d'arcs",
                    'value' => $equipment->getNbBows(),
                ],
                [
                    'label' => 'Orientation',
                    'value' => $equipment->getOrientation() ?: null,
                ],
            ],

            default => [],
        };
    }
}
