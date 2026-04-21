<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Glove;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Glove>
 */
final class GloveFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Glove::class;
    }

    /**
     * @return array<string, int>
     */
    protected function defaults(): array
    {
        return [
            'nb_fingers' => self::faker()->numberBetween(3, 5),
            'size' => self::faker()->numberBetween(6, 12),
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
    }
}
