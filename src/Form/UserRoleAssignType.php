<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\RoleAssignDTO;
use App\Entity\Club;
use App\Entity\Region;
use App\Enum\UserRole;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire d'affectation de rôle à un utilisateur.
 *
 * Options attendues :
 *   - assignable_roles   : UserRole[]      — rôles autorisés pour l'utilisateur connecté
 *   - club_query_builder : QueryBuilder|null — filtre les clubs disponibles
 *   - region_query_builder : QueryBuilder|null — filtre les régions disponibles
 *
 * @extends AbstractType<RoleAssignDTO>
 */
class UserRoleAssignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var UserRole[] $assignableRoles */
        $assignableRoles = $options['assignable_roles'];

        // Construction des choix de rôle label → value
        $roleChoices = [];
        foreach ($assignableRoles as $role) {
            $roleChoices[$role->label()] = $role->value;
        }

        $builder->add('newRole', ChoiceType::class, [
            'label'       => 'Nouveau rôle',
            'choices'     => $roleChoices,
            'placeholder' => '--- choisir un rôle ---',
            'required'    => true,
        ]);

        // Champ club — affiché pour ROLE_CLUB_PRESIDENT et ROLE_EQUIPMENT_MANAGER_CLUB
        $clubOptions = [
            'class'        => Club::class,
            'choice_label' => 'name',
            'placeholder'  => '--- aucun club ---',
            'required'     => false,
            'label'        => 'Club associé',
        ];
        if ($options['club_query_builder'] instanceof QueryBuilder) {
            $clubOptions['query_builder'] = $options['club_query_builder'];
        }

        $builder->add('club', EntityType::class, $clubOptions);

        // Champ régions — affiché pour ROLE_EQUIPMENT_MANAGER_CTK
        $regionOptions = [
            'class'        => Region::class,
            'choice_label' => 'name',
            'multiple'     => true,
            'expanded'     => false,
            'required'     => false,
            'label'        => 'Régions gérées',
            'by_reference' => false,
        ];
        if ($options['region_query_builder'] instanceof QueryBuilder) {
            $regionOptions['query_builder'] = $options['region_query_builder'];
        }

        $builder->add('managedRegions', EntityType::class, $regionOptions);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'           => RoleAssignDTO::class,
            'assignable_roles'     => [],
            'club_query_builder'   => null,
            'region_query_builder' => null,
        ]);

        $resolver->setAllowedTypes('assignable_roles', 'array');
        $resolver->setAllowedTypes('club_query_builder', [QueryBuilder::class, 'null']);
        $resolver->setAllowedTypes('region_query_builder', [QueryBuilder::class, 'null']);
    }
}
