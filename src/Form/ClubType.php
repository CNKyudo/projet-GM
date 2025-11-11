<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\User;
use App\Entity\Address;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClubType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $labelCallback = function ($entity) {
            if (method_exists($entity, '__toString')) {
                return (string) $entity;
            }
            if (method_exists($entity, 'getName')) {
                return $entity->getName();
            }
            if (method_exists($entity, 'getEmail')) {
                return $entity->getEmail();
            }
            return (string) $entity->getId();
        };

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du club',
            ])
            ->add('president', EntityType::class, [
                'class' => User::class,
                'choice_label' => $labelCallback,
                'choice_value' => 'id',
                'placeholder' => '--- choisir un président ---',
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'required' => false,
            ])
            ->add('address', EntityType::class, [
                'class' => Address::class,
                'choice_label' => $labelCallback,
                'placeholder' => '--- choisir une adresse ---',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Club::class,
        ]);
    }
}