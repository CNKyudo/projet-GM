<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Génère un mot de passe aléatoire sécurisé respectant les contraintes :
 *   - 12 caractères
 *   - Au moins 1 lettre majuscule (A-Z)
 *   - Au moins 1 caractère spécial ($, #, !, @, &, *, %)
 *   - Le reste est composé de caractères alphanumériques
 */
final class PasswordGenerator
{
    private const string UPPERCASE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const string LOWERCASE = 'abcdefghijklmnopqrstuvwxyz';

    private const string DIGITS = '0123456789';

    private const string SPECIAL = '$#!@&*%';

    public function generate(int $length = 12): string
    {
        $password = [];

        // Garantit au moins 1 majuscule
        $password[] = $this->randomChar(self::UPPERCASE);

        // Garantit au moins 1 caractère spécial
        $password[] = $this->randomChar(self::SPECIAL);

        // Complète avec des caractères alphanumériques
        $alphanumeric = self::UPPERCASE.self::LOWERCASE.self::DIGITS;
        for ($i = 0; $i < $length - 2; ++$i) {
            $password[] = $this->randomChar($alphanumeric);
        }

        // Mélange pour éviter un motif prévisible (majuscule toujours en 1ère position)
        shuffle($password);

        return implode('', $password);
    }

    private function randomChar(string $chars): string
    {
        $max = \strlen($chars) - 1;
        if ($max < 0) {
            throw new \InvalidArgumentException('La chaîne de caractères source ne peut pas être vide.');
        }

        return $chars[random_int(0, $max)];
    }
}
