<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Yumi;
use App\Enum\YumiLength;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * @extends AbstractType<Yumi>
 */
class YumiFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('material', TextType::class, [
                'label' => 'Matériau',
                'required' => true,
                'constraints' => [
                    new Length(max: 255),
                ],
                'attr' => [
                    'maxlength' => 255,
                ],
            ])
            ->add('strength', IntegerType::class, [
                'label' => 'Force (kg)',
                'required' => true,
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Force en kg',
                ],
            ])
            ->add('yumiLength', EnumType::class, [
                'class' => YumiLength::class,
                'choice_label' => fn (YumiLength $yumiLengthth): string => $yumiLengthth->label(),
                'label' => 'Taille',
                'placeholder' => 'Choisir une taille...',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Yumi::class,
            'inherit_data' => true,
        ]);
    }
}
