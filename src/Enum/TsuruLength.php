<?php

declare(strict_types=1);

namespace App\Enum;

enum TsuruLength: string
{
    case NAMISUN = 'namisun';
    case NISUN_NOBI = 'nisun_nobi';
    case YONSUN_NOBI = 'yonsun_nobi';

    public function label(): string
    {
        return match($this) {
            self::NAMISUN => 'tsuru.length.namisun',
            self::NISUN_NOBI => 'tsuru.length.nisun_nobi',
            self::YONSUN_NOBI => 'tsuru.length.yonsun_nobi',
        };
    }
}
