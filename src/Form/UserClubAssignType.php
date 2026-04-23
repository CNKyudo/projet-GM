<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Club;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Repository\ClubRepository;

/**
 * @extends AbstractType<User>
 */
class UserClubAssignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User|null $currentUser */
        $currentUser = $options['current_user'];

        $clubQb = $this->buildClubQueryBuilder($currentUser);

        $builder
            ->add('clubWhichImPresidentOf', EntityType::class, [
                'class'         => Club::class,
                'choice_label'  => 'name',
                'placeholder'   => '--- aucun club ---',
                'required'      => false,
                'label'         => 'Club (président)',
                'query_builder' => $clubQb,
            ])
            ->add('clubWhereImEquipmentManager', EntityType::class, [
                'class'         => Club::class,
                'choice_label'  => 'name',
                'placeholder'   => '--- aucun club ---',
                'required'      => false,
                'label'         => 'Club (gestionnaire matériel)',
                'query_builder' => $clubQb,
            ])
            ->add('memberOfClubs', EntityType::class, [
                'class'         => Club::class,
                'choice_label'  => 'name',
                'multiple'      => true,
                'expanded'      => false,
                'required'      => false,
                'label'         => 'Clubs (membre)',
                'by_reference'  => false,
                'query_builder' => $clubQb,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'   => User::class,
            'current_user' => null,
        ]);
        $resolver->setAllowedTypes('current_user', ['null', User::class]);
    }

    /**
     * Retourne un QueryBuilder filtrant les clubs selon le rôle du connecté :
     *  - EQUIPMENT_MANAGER_CLUB → uniquement son propre club
     *  - Autres rôles autorisés → tous les clubs (null = pas de filtre)
     *
     * @return callable(ClubRepository): QueryBuilder|null
     */
    private function buildClubQueryBuilder(?User $currentUser): ?callable
    {
        if (!$currentUser instanceof User) {
            return null;
        }

        $roles = $currentUser->getRoles();

        // MANAGER_CLUB : restreint à son propre club
        if (\in_array(UserRole::EQUIPMENT_MANAGER_CLUB->value, $roles, true)
            && !\in_array(UserRole::EQUIPMENT_MANAGER_CTK->value, $roles, true)
            && !\in_array(UserRole::ADMIN->value, $roles, true)) {
            $ownClub = $currentUser->getClubWhereImEquipmentManager();

            if (!$ownClub instanceof Club) {
                // Pas de club associé → liste vide
                return static fn (ClubRepository $repo): QueryBuilder => $repo
                    ->createQueryBuilder('c')
                    ->andWhere('1 = 0');
            }

            $clubId = $ownClub->getId();

            return static fn (ClubRepository $repo): QueryBuilder => $repo
                ->createQueryBuilder('c')
                ->andWhere('c.id = :clubId')
                ->setParameter('clubId', $clubId)
                ->orderBy('c.name', 'ASC');
        }

        // Tous les autres rôles autorisés → tous les clubs
        return static fn (ClubRepository $repo): QueryBuilder => $repo
            ->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC');
    }
}
