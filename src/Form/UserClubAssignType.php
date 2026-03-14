<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<User>
 */
class UserClubAssignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('clubWhichImPresidentOf', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'name',
                'placeholder' => '--- aucun club ---',
                'required' => false,
                'label' => 'Club attribué',
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
