<?php

declare(strict_types=1);

namespace App\Enum;

enum MakiwaraMaterial: string
{
    case STRAW = 'straw';
    case CARDBOARD = 'cardboard';
    case FOAM = 'foam';

    public function label(): string
    {
        return match($this) {
            self::STRAW => 'makiwara.material.straw',
            self::CARDBOARD => 'makiwara.material.cardboard',
            self::FOAM => 'makiwara.material.foam',
        };
    }
}
