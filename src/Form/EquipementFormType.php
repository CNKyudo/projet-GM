<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EquipementFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('createdAt')
            ->add('updatedAt')
            ->add('owner_club', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'id',
            ])
            ->add('borrower_club', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'id',
            ])
            ->add('borrower_user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipment::class,
        ]);
    }
}
