<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Club;
use App\Entity\Region;
use App\Entity\User;
use App\Repository\ClubRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @extends AbstractType<Club>
 */
class ClubType extends AbstractType
{
    public function __construct(
        private readonly ClubRepository $clubRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du club',
            ])
            ->add('region', EntityType::class, [
                'class' => Region::class,
                'choice_label' => 'name',
                'placeholder' => '--- choisir une région ---',
                'required' => false,
                'label' => 'Région',
            ])
            ->add('president', EntityType::class, [
                'class' => User::class,
                'choice_value' => 'id',
                'placeholder' => '--- choisir un président ---',
                'required' => false,
            ])
            ->add('equipmentManager', EntityType::class, [
                'class' => User::class,
                'choice_value' => 'id',
                'placeholder' => '--- choisir un responsable matériel ---',
                'required' => false,
                'label' => 'Responsable matériel',
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'required' => false,
            ])
            ->add('address', AddressType::class, [
                'required' => false,
                'label' => false,
                'by_reference' => false,
                'require_at_least_one_field' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'  => Club::class,
            'constraints' => [
                new Callback($this->validatePresidentAndEquipmentManager(...)),
            ],
        ]);
    }

    /**
     * Empêche :
     * 1. Qu'un même utilisateur soit à la fois président et gestionnaire
     *    matériel d'un club (rôles mutuellement exclusifs).
     * 2. Qu'un utilisateur déjà président d'un club soit désigné gestionnaire
     *    matériel d'un autre club, et vice versa.
     */
    public function validatePresidentAndEquipmentManager(Club $club, ExecutionContextInterface $context): void
    {
        $president        = $club->getPresident();
        $equipmentManager = $club->getEquipmentManager();

        // Même utilisateur pour les deux rôles dans le même club
        if (
            $president instanceof User
            && $equipmentManager instanceof User
            && $president->getId() === $equipmentManager->getId()
        ) {
            $context
                ->buildViolation('Un utilisateur ne peut pas être à la fois président et responsable matériel d\'un club.')
                ->atPath('equipmentManager')
                ->addViolation();

            return;
        }

        $clubId = $club->getId();

        // Un président ne peut pas être responsable matériel d'un autre club
        if ($president instanceof User) {
            $otherClub = $this->clubRepository->findOneByEquipmentManagerExcluding($president, $clubId);
            if ($otherClub instanceof Club) {
                $context
                    ->buildViolation('Cet utilisateur est déjà responsable matériel d\'un club.')
                    ->atPath('president')
                    ->addViolation();
            }
        }

        // Un responsable matériel ne peut pas être président d'un autre club
        if ($equipmentManager instanceof User) {
            $otherClub = $this->clubRepository->findOneByPresidentExcluding($equipmentManager, $clubId);
            if ($otherClub instanceof Club) {
                $context
                    ->buildViolation('Cet utilisateur est déjà président d\'un club.')
                    ->atPath('equipmentManager')
                    ->addViolation();
            }
        }
    }
}
