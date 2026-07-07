<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Muneate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Length;

/**
 * @extends AbstractType<Muneate>
 */
class MuneateFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('size', TextType::class, [
                'label' => 'Taille',
                'required' => true,
                'constraints' => [
                    new Length(max: 255),
                ],
                'attr' => [
                    'maxlength' => 255,
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
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantité',
                'constraints' => [
                    new Range(min: 1),
                ],
                'required' => true,
                'attr' => [
                    'min' => 1,
                ],
            ]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Muneate::class,
            'inherit_data' => true,
        ]);
    }
}
