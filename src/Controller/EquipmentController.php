<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ClubMember;
use App\Entity\Equipment;
use App\Entity\Federation;
use App\Entity\Glove;
use App\Entity\Makiwara;
use App\Entity\Region;
use App\Entity\SupportMakiwara;
use App\Entity\User;
use App\Entity\Yumi;
use App\Entity\Yumitate;
use App\Entity\Yatate;
use App\Entity\Maku;
use App\Entity\Etafoam;
use App\Enum\EquipmentLevel;
use App\Enum\EquipmentType;
use App\Form\EquipmentFormType;
use App\Repository\EquipmentRepository;
use App\Repository\UserRepository;
use App\Security\EquipmentVisibilityFilterResolver;
use App\Security\Voter\UserPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EquipmentController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly EquipmentRepository $equipmentRepository,
        private readonly PaginatorInterface $paginator,
        private readonly EquipmentVisibilityFilterResolver $visibilityFilterResolver,
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
    ) {
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

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Calcul des filtres de visibilité selon le rôle de l'utilisateur
        $filters = $this->visibilityFilterResolver->resolve($currentUser);

        $borrowedUserId = $request->query->getInt('borrowed', 0);

        $queryBuilder = $this->equipmentRepository->findBySearchStrategy(
            $q,
            $equipmentTypeObj,
            $status,
            $filters['restrictToClubs'],
            $filters['allowedClubsAvailableOnly'],
            $filters['allowedRegions'],
            $filters['onlyAvailableRegional'],
            $filters['includeAllAvailableRegional'],
            $filters['includeNational'],
        );

        if ($borrowedUserId > 0) {
            $borrowerMember = $this->resolveBorrowerMember($borrowedUserId);
            $alias = $queryBuilder->getRootAliases()[0];

            if ($borrowerMember instanceof ClubMember) {
                $queryBuilder
                    ->andWhere(sprintf('%s.borrowerMember = :borrowerMember', $alias))
                    ->setParameter('borrowerMember', $borrowerMember);
            } else {
                $queryBuilder->andWhere('1 = 0');
            }
        }

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
            'borrowed' => $borrowedUserId,
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
                    'backHref' => null,
                ]);
            }

            $equipment = match ($type) {
                EquipmentType::YUMI => new Yumi(),
                EquipmentType::GLOVE => new Glove(),
                EquipmentType::MAKIWARA => new Makiwara(),
                EquipmentType::SUPPORT_MAKIWARA => new SupportMakiwara(),
                EquipmentType::YUMITATE => new Yumitate(),
                EquipmentType::YATATE => new Yatate(),
                EquipmentType::MAKU => new Maku(),
                EquipmentType::ETAFOAM => new Etafoam(),
            };

            // Déterminer le niveau et le propriétaire selon les champs soumis
            $ownerFederation = $form->has('ownerFederation') ? $form->get('ownerFederation')->getData() : null;
            $ownerRegion     = $form->has('ownerRegion') ? $form->get('ownerRegion')->getData() : null;
            $ownerClub       = $form->has('ownerClub') ? $form->get('ownerClub')->getData() : null;

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

            $equipment->setBorrowerClub($form->get('borrowerClub')->getData());
            $equipment->setBorrowerMember($form->get('borrowerMember')->getData());

            if ($equipment instanceof Glove && $form->has('glove_form')) {
                $gloveForm = $form->get('glove_form');
                $equipment->setNbFingers($gloveForm->get('nb_fingers')->getData());
                $equipment->setSize($gloveForm->get('size')->getData());
            }

            if ($equipment instanceof Yumi && $form->has('yumi_form')) {
                $yumiForm = $form->get('yumi_form');
                $equipment->setMaterial($yumiForm->get('material')->getData());
                $equipment->setStrength($yumiForm->get('strength')->getData());
                $equipment->setYumiLength($yumiForm->get('yumiLength')->getData());
            }

            if ($equipment instanceof Makiwara && $form->has('makiwara_form')) {
                $makiwaraForm = $form->get('makiwara_form');
                $equipment->setMaterial($makiwaraForm->get('material')->getData());
            }

            if ($equipment instanceof SupportMakiwara && $form->has('support_makiwara_form')) {
                $supportMakiwaraForm = $form->get('support_makiwara_form');
                $equipment->setHeight($supportMakiwaraForm->get('height')->getData());
            }

            if ($equipment instanceof Yumitate && $form->has('yumitate_form')) {
                $yumitateForm = $form->get('yumitate_form');
                $equipment->setNbBows($yumitateForm->get('nb_bows')->getData());
                $equipment->setOrientation($yumitateForm->get('orientation')->getData());
            }

            if ($equipment instanceof Yatate && $form->has('yatate_form')) {
                $yatateForm = $form->get('yatate_form');
                $equipment->setNbArrows($yatateForm->get('nb_arrows')->getData());
            }

            if ($equipment instanceof Maku && $form->has('maku_form')) {
                $makuForm = $form->get('maku_form');
                $equipment->setEquipmentLength($makuForm->get('equipmentLength')->getData());
                $equipment->setHeight($makuForm->get('height')->getData());
                $equipment->setMaterial($makuForm->get('material')->getData());
                $equipment->setWeight($makuForm->get('weight')->getData());
                $equipment->setAttachment($makuForm->get('attachment')->getData());
            }

            if ($equipment instanceof Etafoam && $form->has('etafoam_form')) {
                $etafoamForm = $form->get('etafoam_form');
                $equipment->setEquipmentLength($etafoamForm->get('equipmentLength')->getData());
                $equipment->setWidth($etafoamForm->get('width')->getData());
                $equipment->setThickness($etafoamForm->get('thickness')->getData());
            }

            $entityManager->persist($equipment);
            $entityManager->flush();

            $this->addFlash('success', $this->translator->trans('equipment.added', ['{type}' => $this->translator->trans('equipment.type.'.$equipment->getTypeName())]));

            return $this->redirectToRoute('equipment.index');
        }

        return $this->render('equipment/create.html.twig', [
            'form' => $form,
            'backHref' => null,
        ]);
    }

    #[Route('/equipment/{id}/edit', name: 'equipment.edit', requirements: ['id' => '\d+'])]
    public function edit(Equipment $equipment, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(UserPermissionVoter::EDIT_EQUIPMENT, $equipment);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $backHref = $this->resolveBackHrefFromSession($request, 'equipment_edit_back_href_'.$equipment->getId(), $equipment);

        $form = $this->createForm(EquipmentFormType::class, $equipment, [
            'current_user' => $currentUser,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recalculer le niveau et le propriétaire selon les champs soumis
            // Priorité : fédération > région > club (en cas de champs multiples renseignés)
            $ownerFederation = $form->has('ownerFederation') ? $form->get('ownerFederation')->getData() : null;
            $ownerRegion     = $form->has('ownerRegion') ? $form->get('ownerRegion')->getData() : null;
            $ownerClub       = $form->has('ownerClub') ? $form->get('ownerClub')->getData() : null;

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
            'backHref' => $backHref,
        ]);
    }

    private function resolveBackHrefFromSession(Request $request, string $sessionKey, ?Equipment $equipment = null): string
    {
        $session = $request->getSession();

        if ($request->isMethod('GET')) {
            $backHref = $this->resolveBackHref($request, $equipment);
            $session->set($sessionKey, $backHref);

            return $backHref;
        }

        return $session->get($sessionKey) ?: $this->resolveBackHref($request, $equipment);
    }

    private function resolveBackHref(Request $request, ?Equipment $equipment = null): string
    {
        $referer = $request->headers->get('referer');

        if (null !== $referer) {
            $refererPath = parse_url($referer, PHP_URL_PATH);

            if ($equipment instanceof Equipment) {
                $showPath = $this->generateUrl('equipment.show', ['id' => $equipment->getId()]);

                if ($refererPath === $showPath) {
                    return $refererPath;
                }
            }
        }

        return $this->generateUrl('equipment.index');
    }

    private function resolveBorrowerMember(int $userId): ?ClubMember
    {
        $user = $this->userRepository->find($userId);

        return $user?->getClubMember();
    }
}
