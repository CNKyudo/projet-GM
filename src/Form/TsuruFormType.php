<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tsuru;
use App\Enum\YumiLength;
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
            ->add('tsuru_length', EnumType::class, [
                'class' => YumiLength::class,
                'choice_label' => fn (YumiLength $tsuru_length): string => $tsuru_length->label(),
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
