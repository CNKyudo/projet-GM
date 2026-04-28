<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Maku;
use App\Form\DataTransformer\FrenchNumberTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Maku>
 */
class MakuFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('longueur', TextType::class, [
                'label' => 'Longueur (m)',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: 1,80',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('hauteur', TextType::class, [
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
            ])
            ->add('poids', TextType::class, [
                'label' => 'Poids (kg)',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: 2,5',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('accroche', TextType::class, [
                'label' => 'Accroche',
                'required' => false,
            ]);

        $frenchNumberTransformer = new FrenchNumberTransformer();
        $builder->get('longueur')->addModelTransformer($frenchNumberTransformer);
        $builder->get('hauteur')->addModelTransformer($frenchNumberTransformer);
        $builder->get('poids')->addModelTransformer($frenchNumberTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Maku::class,
            'inherit_data' => true,
        ]);
    }
}
