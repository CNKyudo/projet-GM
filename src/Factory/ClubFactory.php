<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Club;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Club>
 */
final class ClubFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Club::class;
    }

    /**
     * @return array<string, string>
     */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->company(),
            'email' => self::faker()->optional()->companyEmail(),
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
    }
}
