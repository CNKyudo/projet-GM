<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Yumitate;
use App\Enum\YumitateOrientation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/**
 * @extends AbstractType<Yumitate>
 */
class YumitateFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nb_arcs', IntegerType::class, [
                'label' => 'Nombre d\'arcs',
                'constraints' => [
                    new Range(min: 0),
                ],
                'required' => true,
                'attr' => [
                    'min' => 0,
                ],
            ])
            ->add('orientation', EnumType::class, [
                'class' => YumitateOrientation::class,
                'choice_label' => fn (YumitateOrientation $orientation): string => $orientation->label(),
                'label' => 'Orientation',
                'placeholder' => 'Choisir une orientation...',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Yumitate::class,
            'inherit_data' => true,
        ]);
    }
}
