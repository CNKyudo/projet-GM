<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Yatate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/**
 * @extends AbstractType<Yatate>
 */
class YatateFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nb_arrows', IntegerType::class, [
                'label' => 'Nombre de flèches',
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
            'data_class' => Yatate::class,
            'inherit_data' => true,
        ]);
    }
}
