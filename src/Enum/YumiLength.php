<?php

declare(strict_types=1);

namespace App\Enum;

enum YumiLength: string
{
    case NAMISUN = 'namisun';
    case NISUN_NOBI = 'nisun_nobi';
    case YONSUN_NOBI = 'yonsun_nobi';

    public function label(): string
    {
        return match($this) {
            self::NAMISUN => 'yumi.length.namisun',
            self::NISUN_NOBI => 'yumi.length.nisun_nobi',
            self::YONSUN_NOBI => 'yumi.length.yonsun_nobi',
        };
    }
}
