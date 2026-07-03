<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;

/**
 * Formulaire de changement de mot de passe forcé (première connexion).
 *
 * Ne demande pas l'ancien mot de passe : celui-ci a été généré aléatoirement
 * par l'administrateur et l'utilisateur n'est pas censé le conserver.
 *
 * @extends AbstractType<mixed>
 */
class ForceChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'options' => [
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'first_options' => [
                'label' => 'Nouveau mot de passe',
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir un nouveau mot de passe.'),
                    new Length(
                        min: 12,
                        max: 4096,
                        minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                    ),
                    new PasswordStrength(),
                ],
            ],
            'second_options' => [
                'label' => 'Confirmez votre mot de passe',
            ],
            'invalid_message' => 'Les deux mots de passe doivent être identiques.',
            'mapped' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
