<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Yugake;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Yugake>
 */
final class YugakeFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Yugake::class;
    }

    /**
     * @return array<string, int|string>
     */
    protected function defaults(): array
    {
        return [
            'nb_fingers' => self::faker()->numberBetween(3, 5),
            'size' => (string) self::faker()->numberBetween(6, 12),
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
    }
}
