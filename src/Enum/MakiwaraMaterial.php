<?php

declare(strict_types=1);

namespace App\Enum;

enum MakiwaraMaterial: string
{
    case PAILLE = 'paille';
    case CARTON = 'carton';
    case MOUSSE = 'mousse';

    public function label(): string
    {
        return match($this) {
            self::PAILLE => 'makiwara.material.paille',
            self::CARTON => 'makiwara.material.carton',
            self::MOUSSE => 'makiwara.material.mousse',
        };
    }
}
