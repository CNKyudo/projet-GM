<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Glove;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/**
 * @extends AbstractType<Glove>
 */
class GloveFormType extends AbstractType
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
            ->add('size', IntegerType::class, [
                'label' => 'Taille',
                'constraints' => [
                    new Range(
                        notInRangeMessage: 'La taille doit être entre 3 et 11',
                        min: 3,
                        max: 11,
                    ),
                ],
                'required' => false,
                'attr' => [
                    'min' => 3,
                    'max' => 11,
                    'placeholder' => 'Entre 3 et 11',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Glove::class,
            'inherit_data' => true,
        ]);
    }
}
