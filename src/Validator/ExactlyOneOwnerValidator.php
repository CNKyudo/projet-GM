<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Club;
use App\Entity\Federation;
use App\Entity\Region;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Valide que parmi les champs owner_federation / owner_region / owner_club
 * présents dans les données du formulaire, exactement un est renseigné.
 *
 * Le validator est appliqué au niveau de la classe du FormData (objet stdClass
 * ou tableau normalisé par Symfony Form). En pratique, EquipmentFormType passe
 * un objet \stdClass ou null pour les champs non-mappés ; on travaille donc
 * directement sur le tableau de données brutes via l'objet de contrainte.
 *
 * Le validator est déclenché depuis EquipmentFormType via
 * 'constraints' => [new ExactlyOneOwner()] sur le formulaire.
 * L'objet $value est le tableau de données du formulaire après normalisation.
 */
class ExactlyOneOwnerValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ExactlyOneOwner) {
            throw new UnexpectedTypeException($constraint, ExactlyOneOwner::class);
        }

        // $value est le tableau de données du formulaire (champs non-mappés inclus)
        // Symfony Form passe ici les valeurs déjà transformées (entités Doctrine ou null).
        if (!\is_array($value) && !\is_object($value)) {
            return;
        }

        $get = static function (mixed $data, string $key): mixed {
            if (\is_array($data)) {
                return $data[$key] ?? null;
            }

            if (\is_object($data) && isset($data->$key)) {
                return $data->$key;
            }

            return null;
        };

        $federation = $get($value, 'owner_federation');
        $region     = $get($value, 'owner_region');
        $club       = $get($value, 'owner_club');

        // Normalise : seules les entités Doctrine comptent comme "renseignées"
        $hasFederation = $federation instanceof Federation;
        $hasRegion     = $region instanceof Region;
        $hasClub       = $club instanceof Club;

        // Détermine quels champs sont présents dans le formulaire (selon le rôle)
        $presentFields = [];
        if (null !== $federation || \array_key_exists('owner_federation', (array) $value)) {
            $presentFields[] = 'owner_federation';
        }

        if (null !== $region || \array_key_exists('owner_region', (array) $value)) {
            $presentFields[] = 'owner_region';
        }

        if (null !== $club || \array_key_exists('owner_club', (array) $value)) {
            $presentFields[] = 'owner_club';
        }

        // Si aucun champ owner n'est présent dans le formulaire, on ne valide pas
        // (le rôle n'expose pas ces champs — ne devrait pas arriver en pratique)
        if ([] === $presentFields) {
            return;
        }

        $filledCount = (int) $hasFederation + (int) $hasRegion + (int) $hasClub;

        if (0 === $filledCount) {
            $this->context->buildViolation($constraint->messageNone)
                ->atPath('owner_club')
                ->addViolation();

            return;
        }

        if ($filledCount > 1) {
            $this->context->buildViolation($constraint->messageMultiple)
                ->atPath('owner_federation')
                ->addViolation();
        }
    }
}
