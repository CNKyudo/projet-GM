<?php

namespace App\Form;

use App\Entity\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Address>
 */
class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('streetAddress', TextType::class, [
                'label' => 'Rue',
                'required' => false,
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'required' => false,
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($options): void {
            if (($options['require_at_least_one_field'] ?? true) !== true) {
                return;
            }

            $address = $event->getData();
            if (!$address instanceof Address) {
                return;
            }

            $values = [
                $address->getStreetAddress(),
                $address->getPostalCode(),
                $address->getCity(),
                $address->getCountry(),
            ];

            foreach ($values as $value) {
                if (is_string($value) && trim($value) !== '') {
                    return;
                }
            }

            $event->getForm()->addError(new FormError('Veuillez renseigner au moins un champ d\'adresse.'));
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
            'is_edit' => false,
            'require_at_least_one_field' => true,
        ]);
    }
}
