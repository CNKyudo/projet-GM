<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @implements DataTransformerInterface<float|null, string>
 */
final class FrenchNumberTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        return (string) $value;
    }

    public function reverseTransform(mixed $value): ?float
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $normalized = str_replace(',', '.', (string) $value);

        if (!is_numeric($normalized)) {
            throw new TransformationFailedException(\sprintf('La valeur "%s" n\'est pas un nombre valide.', $value));
        }

        return (float) $normalized;
    }
}
