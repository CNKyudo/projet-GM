<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<User>
 */
final class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'autocomplete' => 'email',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner une adresse email.'),
                    new Email(message: 'Veuillez renseigner une adresse email valide.'),
                ],
            ])
            ->add('currentPassword', PasswordType::class, [
                'mapped' => false,
                'label' => 'Mot de passe actuel',
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre mot de passe pour confirmer ce changement.'),
                    new UserPassword(message: 'Le mot de passe est incorrect.'),
                ],
                'attr' => [
                    'autocomplete' => 'current-password',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
