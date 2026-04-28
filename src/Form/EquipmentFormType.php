<?php

declare(strict_types=1);

namespace App\Form;

use Doctrine\ORM\QueryBuilder;
use App\Entity\Club;
use App\Entity\ClubMember;
use App\Entity\Equipment;
use App\Entity\Federation;
use App\Entity\Glove;
use App\Entity\Makiwara;
use App\Entity\Region;
use App\Entity\User;
use App\Entity\Yumi;
use App\Enum\EquipmentType;
use App\Repository\ClubRepository;
use App\Security\UserPermissionService;
use App\Validator\ExactlyOneOwner;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Equipment>
 */
class EquipmentFormType extends AbstractType
{
    public function __construct(private readonly UserPermissionService $userPermissionService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User|null $currentUser */
        $currentUser = $options['current_user'];

        $this->addOwnerFields($builder, $currentUser);

        $builder
            ->add('borrowerClub', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'name',
                'placeholder' => '--- Aucun ---',
                'required' => false,
            ])
            ->add('borrowerMember', EntityType::class, [
                'class' => ClubMember::class,
                'choice_label' => 'fullName',
                'placeholder' => '--- Aucun ---',
                'required' => false,
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $formEvent): void {
            $form = $formEvent->getForm();
            $data = $formEvent->getData();

            if (empty($data)) {
                $form
                    ->add('equipment_type', EnumType::class, [
                        'class' => EquipmentType::class,
                        'choice_value' => fn (?EquipmentType $equipmentType) => $equipmentType?->value,
                        'choice_label' => fn (EquipmentType $equipmentType): string => $equipmentType->label(),
                        'choice_attr' => fn (EquipmentType $equipmentType): array => ['data-equipment-type' => $equipmentType->value],
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
                    ->add('makiwara_form', MakiwaraFormType::class, [
                        'disabled' => true,
                    ])
                ;
            } elseif ($data instanceof Equipment) {
                // Reconstruire les champs owner* avec l'option `data` pour que le select
                // affiche la bonne valeur pré-sélectionnée (setData() ne suffit pas pour
                // les champs mapped:false avec EntityType).
                $currentUser = $form->getConfig()->getOption('current_user');
                $this->addOwnerFields($form, $currentUser, $data);

                if ($data instanceof Glove) {
                    $form->add('glove_form', GloveFormType::class);
                } elseif ($data instanceof Yumi) {
                    $form->add('yumi_form', YumiFormType::class);
                } elseif ($data instanceof Makiwara) {
                    $form->add('makiwara_form', MakiwaraFormType::class);
                }
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $formEvent): void {
            $form = $formEvent->getForm();
            $data = $formEvent->getData();

            if (!$form->has('equipment_type')) {
                return;
            }

            $submittedType = is_array($data) ? ($data['equipment_type'] ?? null) : null;

            $form->add('glove_form', GloveFormType::class, [
                'disabled' => $submittedType !== EquipmentType::GLOVE->value,
            ]);

            $form->add('yumi_form', YumiFormType::class, [
                'disabled' => $submittedType !== EquipmentType::YUMI->value,
            ]);

            $form->add('makiwara_form', MakiwaraFormType::class, [
                'disabled' => $submittedType !== EquipmentType::MAKIWARA->value,
            ]);
        });

        // Validation cross-champs : au moins un propriétaire parmi
        // ownerFederation / ownerRegion / ownerClub doit être renseigné.
        // En cas de plusieurs renseignés, la priorité est : fédération > région > club
        // (le contrôleur applique la même priorité).
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $formEvent): void {
            $form = $formEvent->getForm();

            $federation = $form->has('ownerFederation') ? $form->get('ownerFederation')->getData() : null;
            $region     = $form->has('ownerRegion') ? $form->get('ownerRegion')->getData() : null;
            $club       = $form->has('ownerClub') ? $form->get('ownerClub')->getData() : null;

            // Si aucun champ owner n'est présent dans le formulaire
            // (rôle sans champ propriétaire exposé), on ne valide pas.
            $hasAnyOwnerField = $form->has('ownerFederation')
                || $form->has('ownerRegion')
                || $form->has('ownerClub');

            if (!$hasAnyOwnerField) {
                return;
            }

            $exactlyOneOwner = new ExactlyOneOwner();
            $filledCount = (int) ($federation instanceof Federation)
                + (int) ($region instanceof Region)
                + (int) ($club instanceof Club);

            if (0 === $filledCount) {
                $form->addError(new FormError($exactlyOneOwner->messageNone));
            }

            // Si plusieurs sont renseignés, on ne rejette plus : le contrôleur
            // applique la priorité fédération > région > club.
        });

        // Validation cross-champs : borrowerClub et borrowerMember sont mutuellement exclusifs.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $formEvent): void {
            $form = $formEvent->getForm();

            $borrowerClub   = $form->get('borrowerClub')->getData();
            $borrowerMember = $form->get('borrowerMember')->getData();

            if ($borrowerClub instanceof Club && $borrowerMember instanceof ClubMember) {
                $form->get('borrowerMember')->addError(
                    new FormError('Un équipement ne peut avoir qu\'un seul emprunteur : choisissez soit un club, soit un membre, pas les deux.')
                );
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'current_user' => null,
        ]);
        $resolver->setAllowedTypes('current_user', ['null', User::class]);
    }

    /**
     * Ajoute les champs de propriétaire selon le rôle de l'utilisateur courant :
     *
     *  - CN / ADMIN           → ownerFederation (fédération) OU ownerRegion OU ownerClub (tout club)
     *  - CTK                  → ownerRegion (ses régions) OU ownerClub (clubs de ses régions)
     *  - PRESIDENT / MANAGER_CLUB → ownerClub (son club uniquement, pré-sélectionné)
     *  - null / autre          → ownerClub (tous les clubs, fallback)
     */
    /**
     * @param FormBuilderInterface<mixed>|FormInterface<mixed> $builder
     */
    private function addOwnerFields(FormBuilderInterface|FormInterface $builder, ?User $user, ?Equipment $equipment = null): void
    {
        $isCnOrAdmin            = $user instanceof User && $this->userPermissionService->canCreateNationalEquipment($user);
        $isCtk                  = $user instanceof User && !$isCnOrAdmin && $this->userPermissionService->canCreateRegionalEquipment($user);
        $isPresidentOrManagerClub = $user instanceof User && !$isCnOrAdmin && !$isCtk && $this->userPermissionService->canCreateOwnClubEquipment($user);

        if ($isCnOrAdmin) {
            // Peut créer pour la fédération OU pour n'importe quel club
            $builder->add('ownerFederation', EntityType::class, [
                'class' => Federation::class,
                'choice_label' => 'name',
                'placeholder' => '--- Aucune fédération ---',
                'required' => false,
                'mapped' => false,
                'label' => 'Fédération propriétaire (national)',
                'data' => $equipment?->getOwnerFederation(),
            ]);
            $builder->add('ownerRegion', EntityType::class, [
                'class' => Region::class,
                'choice_label' => 'name',
                'placeholder' => '--- Aucune région ---',
                'required' => false,
                'mapped' => false,
                'label' => 'Région propriétaire (régional)',
                'data' => $equipment?->getOwnerRegion(),
            ]);
            $builder->add('ownerClub', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'name',
                'placeholder' => '--- Aucun club ---',
                'required' => false,
                'mapped' => false,
                'label' => 'Club propriétaire (club)',
                'data' => $equipment?->getOwnerClub(),
            ]);
        } elseif ($isCtk) {
            // Peut créer pour une de ses régions OU un club de ses régions
            $managedRegions = $user->getManagedRegions();

            $builder->add('ownerRegion', EntityType::class, [
                'class' => Region::class,
                'choice_label' => 'name',
                'choices' => $managedRegions,
                'placeholder' => '--- Aucune région ---',
                'required' => false,
                'mapped' => false,
                'label' => 'Région propriétaire (régional)',
                'data' => $equipment?->getOwnerRegion(),
            ]);
            $builder->add('ownerClub', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'name',
                'query_builder' => fn (ClubRepository $clubRepository): QueryBuilder => $clubRepository->createQueryBuilder('c')
                    ->where('c.region IN (:regions)')
                    ->setParameter('regions', $managedRegions->toArray())
                    ->orderBy('c.name', 'ASC'),
                'placeholder' => '--- Aucun club ---',
                'required' => false,
                'mapped' => false,
                'label' => 'Club propriétaire (club)',
                'data' => $equipment?->getOwnerClub(),
            ]);
        } elseif ($isPresidentOrManagerClub) {
            // Peut créer uniquement pour son propre club
            $ownClub = $user->getClubWhichImPresidentOf()
                ?? $user->getClubWhereImEquipmentManager();

            $builder->add('ownerClub', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'name',
                'choices' => $ownClub instanceof Club ? [$ownClub] : [],
                'data' => $equipment?->getOwnerClub() ?? $ownClub,
                'required' => true,
                'mapped' => false,
                'label' => 'Club propriétaire',
            ]);
        } else {
            // Fallback (ne devrait pas arriver, accès refusé en amont)
            $builder->add('ownerClub', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'name',
                'placeholder' => '--- Aucun club ---',
                'required' => false,
                'mapped' => false,
                'data' => $equipment?->getOwnerClub(),
            ]);
        }
    }
}
