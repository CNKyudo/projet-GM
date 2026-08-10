<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tsuru;
use App\Enum\TsuruLength;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/**
 * @extends AbstractType<Tsuru>
 */
class TsuruFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tsuruLength', EnumType::class, [
                'class' => TsuruLength::class,
                'choice_label' => fn (TsuruLength $tsuruLength): string => $tsuruLength->label(),
                'label' => 'Taille',
                'placeholder' => 'Choisir une taille...',
                'required' => true,
            ])
            ->add('strength_min', NumberType::class, [
                'label' => 'Force Min (kg)',
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Force en kg',
                ],
            ])
            ->add('strength_max', NumberType::class, [
                'label' => 'Force Max (kg)',
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Force en kg',
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
            'data_class' => Tsuru::class,
        ]);
    }
}
