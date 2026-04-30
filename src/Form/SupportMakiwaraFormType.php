<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SupportMakiwara;
use App\Form\DataTransformer\FrenchNumberTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SupportMakiwara>
 */
class SupportMakiwaraFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('height', TextType::class, [
                'label' => 'Hauteur (m)',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: 1,80',
                    'inputmode' => 'decimal',
                ],
            ]);

        $builder->get('height')
            ->addModelTransformer(new FrenchNumberTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SupportMakiwara::class,
            'inherit_data' => true,
        ]);
    }
}
