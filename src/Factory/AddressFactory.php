<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Address;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Address>
 */
final class AddressFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Address::class;
    }

    /**
     * @return array<string, string>
     */
    protected function defaults(): array
    {
        return [
            'street_address' => self::faker()->streetAddress(),
            'postal_code' => self::faker()->postcode(),
            'city' => self::faker()->city(),
            'country' => 'France',
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
    }
}
