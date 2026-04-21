<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Contrainte de formulaire : parmi owner_federation, owner_region, owner_club,
 * exactement UN doit être renseigné au moment de la soumission.
 *
 * S'applique sur l'objet FormData (niveau formulaire entier).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ExactlyOneOwner extends Constraint
{
    public string $messageNone = 'Vous devez choisir un propriétaire : fédération, région ou club.';

    public string $messageMultiple = 'Un seul propriétaire est autorisé : choisissez soit la fédération, soit la région, soit le club.';

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
