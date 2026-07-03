<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire de création d'utilisateur par un administrateur.
 * Le mot de passe est généré automatiquement — seul l'email est saisi.
 *
 * @extends AbstractType<User>
 */
class AdminUserCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Adresse email',
            'constraints' => [
                new NotBlank(message: 'Veuillez entrer une adresse email.'),
                new Email(message: "L'adresse email n'est pas valide."),
                new Length(max: 180, maxMessage: "L'adresse email ne peut pas dépasser {{ limit }} caractères."),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
