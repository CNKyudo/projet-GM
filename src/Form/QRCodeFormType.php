<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Equipment;
use App\Entity\QRCode;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<QRCode>
 */
class QRCodeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentEquipment = $options['current_equipment'];

        $builder->add('equipment', EntityType::class, [
            'class' => Equipment::class,
            'choice_label' => fn (Equipment $e): string => sprintf('#%d - %s', $e->getId(), ucfirst($e->getTypeName())),
            'placeholder' => '--- Sélectionner un équipement ---',
            'required' => true,
            'label' => 'Équipement',
            'query_builder' => function (EntityRepository $er) use ($currentEquipment): QueryBuilder {
                $qb = $er->createQueryBuilder('e')
                    ->leftJoin(QRCode::class, 'q', 'WITH', 'q.equipment = e')
                    ->where('q.id IS NULL');

                // En mode édition, inclure aussi l'équipement actuellement associé
                if (null !== $currentEquipment) {
                    $qb->orWhere('e = :current')
                       ->setParameter('current', $currentEquipment);
                }

                return $qb->orderBy('e.id', 'ASC');
            },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QRCode::class,
            'current_equipment' => null,
        ]);

        $resolver->setAllowedTypes('current_equipment', ['null', Equipment::class]);
    }
}
