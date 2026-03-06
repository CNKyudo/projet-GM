<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Club>
 */
class ClubType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du club',
            ])
            ->add('president', EntityType::class, [
                'class' => User::class,
                'choice_value' => 'id',
                'placeholder' => '--- choisir un président ---',
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'required' => false,
            ])
            ->add('address', AddressType::class, [
                'required' => false,
                'label' => false,
                'by_reference' => false,
                'require_at_least_one_field' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Club::class,
        ]);
    }
}
