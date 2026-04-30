<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Etafoam;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/**
 * @extends AbstractType<Etafoam>
 */
class EtafoamFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('equipmentLength', IntegerType::class, [
                'label' => 'Longueur (cm)',
                'constraints' => [
                    new Range(min: 0),
                ],
                'required' => true,
                'attr' => [
                    'min' => 0,
                ],
            ])
            ->add('width', IntegerType::class, [
                'label' => 'Largeur (cm)',
                'constraints' => [
                    new Range(min: 0),
                ],
                'required' => true,
                'attr' => [
                    'min' => 0,
                ],
            ])
            ->add('thickness', IntegerType::class, [
                'label' => 'Épaisseur (cm)',
                'constraints' => [
                    new Range(min: 0),
                ],
                'required' => true,
                'attr' => [
                    'min' => 0,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Etafoam::class,
            'inherit_data' => true,
        ]);
    }
}
