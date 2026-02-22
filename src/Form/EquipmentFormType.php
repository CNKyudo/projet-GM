<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Glove;
use App\Entity\User;
use App\Entity\Yumi;
use App\Enum\EquipmentType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

/**
 * @extends AbstractType<Equipment>
 */
class EquipmentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('owner_club', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'name',
                'placeholder' => '--- Aucun ---',
                'required' => true,
            ])
            ->add('borrower_club', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'name',
                'placeholder' => '--- Aucun ---',
                'required' => false,
            ])
            ->add('borrower_user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'placeholder' => '--- Aucun ---',
                'required' => false,
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $data = $event->getData();

            if (empty($data)) {
                $form
                    ->add('equipment_type', EnumType::class, [
                        'class' => EquipmentType::class,
                        'choice_value' => fn (?EquipmentType $type) => $type?->value,
                        'choice_label' => fn (EquipmentType $type) => $type->label(),
                        'choice_attr' => fn (EquipmentType $type) => ['data-equipment-type' => $type->value],
                        'label' => 'Type d\'équipement',
                        'placeholder' => 'equipment.choose_type',
                        'translation_domain' => 'messages',
                        'mapped' => false,
                        'required' => true,
                    ])
                    ->add('glove_form', GloveFormType::class, [
                        'disabled' => true,
                    ])
                    ->add('yumi_form', YumiFormType::class, [
                        'disabled' => true,
                    ])
                ;
            } elseif ($data instanceof Glove) {
                $form->add('glove_form', GloveFormType::class);
            } elseif ($data instanceof Yumi) {
                $form->add('yumi_form', YumiFormType::class);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
