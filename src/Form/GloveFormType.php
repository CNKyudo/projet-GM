<?php

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
                    new Range([
                        'min' => 0,
                        'max' => 5,
                    ]),
                ],
                'required' => true,
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
