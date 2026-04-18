<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Equipment;
use App\Entity\Federation;
use App\Entity\Glove;
use App\Entity\Region;
use App\Entity\User;
use App\Entity\Yumi;
use App\Enum\EquipmentLevel;
use App\Enum\EquipmentType;
use App\Form\EquipmentFormType;
use App\Repository\EquipmentRepository;
use App\Security\Voter\UserPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EquipmentController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(private readonly EquipmentRepository $equipmentRepository, private readonly PaginatorInterface $paginator)
    {
    }

    #[Route('/equipment', name: 'equipment.index')]
    #[IsGranted(UserPermissionVoter::BROWSE_ALL_EQUIPMENT)]
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $equipmentType = (string) $request->query->get('equipmentType', '');
        $equipmentTypeObj = EquipmentType::tryFrom($equipmentType);
        $status = (string) $request->query->get('status', 'all');

        // Valider status
        if (!in_array($status, ['all', 'available', 'loaned'], true)) {
            $status = 'all';
        }

        // Tous les rôles autorisés (MEMBER et au-dessus) voient l'intégralité des équipements
        // (club, régional, national). Aucune restriction de visibilité sur l'index.
        // null = pas de restriction → applyOwnershipFilter retourne tout sans filtre.
        $queryBuilder = $this->equipmentRepository->findBySearchStrategy($q, $equipmentTypeObj, $status, null, null, true);

        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE,
        );

        return $this->render('equipment/index.html.twig', [
            'equipments' => $pagination,
            'q' => $q,
            'equipmentType' => $equipmentType,
            'status' => $status,
        ]);
    }

    #[Route('/equipment/{id}', name: 'equipment.show', requirements: ['id' => '\d+'])]
    public function show(Equipment $equipment): Response
    {
        $this->denyAccessUnlessGranted(UserPermissionVoter::VIEW_EQUIPMENT, $equipment);

        return $this->render('equipment/show.html.twig', [
            'equipment' => $equipment,
        ]);
    }

    #[Route('/equipment/create', name: 'equipment.create')]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Un utilisateur doit pouvoir créer au moins un type d'équipement pour accéder à ce formulaire
        $canCreateAnyEquipment =
            $this->isGranted(UserPermissionVoter::CREATE_OWN_CLUB_EQUIPMENT)
            || $this->isGranted(UserPermissionVoter::CREATE_NATIONAL_EQUIPMENT)
            || $this->isGranted(UserPermissionVoter::CREATE_REGIONAL_EQUIPMENT)
            || $this->isGranted(UserPermissionVoter::CREATE_EQUIPMENT_FOR_OTHER_CLUB);

        if (!$canCreateAnyEquipment) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $form = $this->createForm(EquipmentFormType::class, null, [
            'current_user' => $currentUser,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $type = $form->get('equipment_type')->getData();

            if (!$type instanceof EquipmentType) {
                $this->addFlash('error', 'Type d\'équipement non trouvé !');

                return $this->render('equipment/create.html.twig', [
                    'form' => $form,
                ]);
            }

            $equipment = match ($type) {
                EquipmentType::YUMI => new Yumi(),
                EquipmentType::GLOVE => new Glove(),
            };

            // Déterminer le niveau et le propriétaire selon les champs soumis
            $ownerFederation = $form->has('owner_federation') ? $form->get('owner_federation')->getData() : null;
            $ownerRegion     = $form->has('owner_region') ? $form->get('owner_region')->getData() : null;
            $ownerClub       = $form->has('owner_club') ? $form->get('owner_club')->getData() : null;

            if ($ownerFederation instanceof Federation) {
                // Vérification : seuls CN/ADMIN peuvent créer un équipement national
                if (!$this->isGranted(UserPermissionVoter::CREATE_NATIONAL_EQUIPMENT)) {
                    throw $this->createAccessDeniedException();
                }

                $equipment->setEquipmentLevel(EquipmentLevel::NATIONAL);
                $equipment->setOwnerFederation($ownerFederation);
            } elseif ($ownerRegion instanceof Region) {
                // Vérification contextuelle : CTK uniquement pour ses propres régions
                if (!$this->isGranted(UserPermissionVoter::CREATE_REGIONAL_EQUIPMENT)) {
                    throw $this->createAccessDeniedException();
                }

                $equipment->setEquipmentLevel(EquipmentLevel::REGIONAL);
                $equipment->setOwnerRegion($ownerRegion);
            } else {
                // Par défaut : niveau CLUB
                $equipment->setEquipmentLevel(EquipmentLevel::CLUB);
                $equipment->setOwnerClub($ownerClub);
            }

            $equipment->setBorrowerClub($form->get('borrower_club')->getData());
            $equipment->setBorrowerUser($form->get('borrower_user')->getData());

            if ($equipment instanceof Glove && $form->has('glove_form')) {
                $gloveForm = $form->get('glove_form');
                $equipment->setNbFingers($gloveForm->get('nb_fingers')->getData());
                $equipment->setSize($gloveForm->get('size')->getData());
            }

            if ($equipment instanceof Yumi && $form->has('yumi_form')) {
                $yumiForm = $form->get('yumi_form');
                $equipment->setMaterial($yumiForm->get('material')->getData());
                $equipment->setStrength($yumiForm->get('strength')->getData());
                $equipment->setYumiLength($yumiForm->get('length')->getData());
            }

            $entityManager->persist($equipment);
            $entityManager->flush();

            $this->addFlash('success', ucfirst($equipment->getTypeName()).' ajouté !');

            return $this->redirectToRoute('equipment.index');
        }

        return $this->render('equipment/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/equipment/{id}/edit', name: 'equipment.edit', requirements: ['id' => '\d+'])]
    public function edit(Equipment $equipment, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(UserPermissionVoter::EDIT_EQUIPMENT, $equipment);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $form = $this->createForm(EquipmentFormType::class, $equipment, [
            'current_user' => $currentUser,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recalculer le niveau et le propriétaire selon les champs soumis
            // Priorité : fédération > région > club (en cas de champs multiples renseignés)
            $ownerFederation = $form->has('owner_federation') ? $form->get('owner_federation')->getData() : null;
            $ownerRegion     = $form->has('owner_region') ? $form->get('owner_region')->getData() : null;
            $ownerClub       = $form->has('owner_club') ? $form->get('owner_club')->getData() : null;

            if ($ownerFederation instanceof Federation) {
                if (!$this->isGranted(UserPermissionVoter::CREATE_NATIONAL_EQUIPMENT)) {
                    throw $this->createAccessDeniedException();
                }

                $equipment->setEquipmentLevel(EquipmentLevel::NATIONAL);
                $equipment->setOwnerFederation($ownerFederation);
                $equipment->setOwnerRegion(null);
                $equipment->setOwnerClub(null);
            } elseif ($ownerRegion instanceof Region) {
                if (!$this->isGranted(UserPermissionVoter::CREATE_REGIONAL_EQUIPMENT)
                    && !$this->isGranted(UserPermissionVoter::EDIT_EQUIPMENT, $equipment)) {
                    throw $this->createAccessDeniedException();
                }

                $equipment->setEquipmentLevel(EquipmentLevel::REGIONAL);
                $equipment->setOwnerRegion($ownerRegion);
                $equipment->setOwnerFederation(null);
                $equipment->setOwnerClub(null);
            } elseif (null !== $ownerClub) {
                $equipment->setEquipmentLevel(EquipmentLevel::CLUB);
                $equipment->setOwnerClub($ownerClub);
                $equipment->setOwnerFederation(null);
                $equipment->setOwnerRegion(null);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Équipement modifié.');

            return $this->redirectToRoute('equipment.index');
        }

        return $this->render('equipment/edit.html.twig', [
            'equipments' => $equipment,
            'form' => $form,
        ]);
    }
}
