<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Maku;
use App\Form\DataTransformer\FrenchNumberTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * @extends AbstractType<Maku>
 */
class MakuFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('equipmentLength', TextType::class, [
                'label' => 'Longueur (m)',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: 1,80',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('height', TextType::class, [
                'label' => 'Hauteur (m)',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: 1,50',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('material', TextType::class, [
                'label' => 'Matière',
                'required' => true,
                'constraints' => [
                    new Length(max: 255),
                ],
                'attr' => [
                    'maxlength' => 255,
                ],
            ])
            ->add('weight', TextType::class, [
                'label' => 'Poids (kg)',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: 2,5',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('attachment', TextType::class, [
                'label' => 'Accroche',
                'required' => false,
                'constraints' => [
                    new Length(max: 255),
                ],
                'attr' => [
                    'maxlength' => 255,
                ],
            ]);

        $frenchNumberTransformer = new FrenchNumberTransformer();
        $builder->get('equipmentLength')->addModelTransformer($frenchNumberTransformer);
        $builder->get('height')->addModelTransformer($frenchNumberTransformer);
        $builder->get('weight')->addModelTransformer($frenchNumberTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Maku::class,
            'inherit_data' => true,
        ]);
    }
}
