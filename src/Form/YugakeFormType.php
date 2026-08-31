<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Yugake;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;

/**
 * @extends AbstractType<Yugake>
 */
class YugakeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nb_fingers', IntegerType::class, [
                'label' => 'Nombre de doigts',
                'constraints' => [
                    new Range(min: 0, max: 5),
                ],
                'required' => true,
            ])
            ->add('size', TextType::class, [
                'label' => 'Taille',
                'constraints' => [
                    new Length(max: 255),
                ],
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Nombre ou S/M/L',
                ],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Yugake::class,
            'inherit_data' => true,
        ]);
    }
}
