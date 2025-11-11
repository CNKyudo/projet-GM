<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Glove;
use App\Entity\Yumi;
use App\Entity\User;
use App\Enum\EquipmentType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;


class EquipmentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('equipment_type', ChoiceType::class, [
                'choices' => [
                    'Yumi' => EquipmentType::YUMI,
                    'Gant (Kake)' => EquipmentType::GLOVE,
                ],
                'choice_value' => fn (?EquipmentType $enum) => $enum?->value,
                'choice_label' => fn (EquipmentType $enum) =>
                    $enum === EquipmentType::YUMI ? 'Yumi' : 'Kake',
                'placeholder' => 'Choisir un type...',
                'mapped' => false, 
                'required' => true,
            ])
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
            // ->add('createdAt')
            // ->add('updatedAt')
            ->add('save', SubmitType::class, [ 'label' => 'Envoyer' ])
        ;     
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
