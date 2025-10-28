<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\User;
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
            // ->add('type', ChoiceType::class, [
            //     'choices' => [
            //         'Yumi' => Yumi::class,
            //         'Kake' => Glove::class,
            //     ],
            //     'mapped' => false, // très important !
            // ])
            // TODO : define dynamically the types.
            // ->add('type', ChoiceType::class, [
            //     'choices' => $this->helper->getSubclasses(),
            //     'mapped' => false,
            //     'label' => 'Type d’équipement',
            //     'placeholder' => 'Choisir un type',
            // ])
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
            'data_class' => Equipment::class,
        ]);
    }
}
