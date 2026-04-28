<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Makiwara;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Makiwara>
 */
class MakiwaraFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('material', ChoiceType::class, [
                'label' => 'Matériau',
                'choices' => [
                    'Paille' => 'paille',
                    'Carton' => 'carton',
                    'Mousse' => 'mousse',
                ],
                'placeholder' => 'Choisir un matériau...',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Makiwara::class,
            'inherit_data' => true,
        ]);
    }
}
