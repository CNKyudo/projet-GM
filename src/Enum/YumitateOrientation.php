<?php

declare(strict_types=1);

namespace App\Enum;

enum YumitateOrientation: string
{
    case RECTO = 'recto';
    case RECTO_VERSO = 'recto_verso';

    public function label(): string
    {
        return match($this) {
            self::RECTO => 'yumitate.orientation.recto',
            self::RECTO_VERSO => 'yumitate.orientation.recto_verso',
        };
    }
}
