<?php

namespace App\Controller;

use App\Entity\Equipment;
use App\Entity\Glove;
use App\Entity\Yumi;
use App\Enum\EquipmentType;
use App\Form\EquipmentFormType;
use App\Repository\EquipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EquipmentController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;

    #[Route('/equipment', name: 'equipment.index')]
    public function index(Request $request, EquipmentRepository $repository, PaginatorInterface $paginator): Response
    {
        $qb = $repository->createQueryBuilder('e')->orderBy('e.id', 'DESC');

        $equipments = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE,
        );

        return $this->render('equipment/index.html.twig', [
            'equipments' => $equipments,
        ]);
    }

    #[Route('/equipment/{id}', name: 'equipment.show', requirements: ['id' => '\d+'])]
    public function show(Equipment $equipment): Response
    {
        return $this->render('equipment/show.html.twig', [
            'equipment' => $equipment,
        ]);
    }

    #[Route('/equipment/create', name: 'equipment.create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EquipmentFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $type = $form->get('equipment_type')->getData();
            $ownerClub = $form->get('owner_club')->getData();
            $borrowerClub = $form->get('borrower_club')->getData();
            $borrowerUser = $form->get('borrower_user')->getData();

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

            $equipment->setOwnerClub($ownerClub);
            $equipment->setBorrowerClub($borrowerClub);
            $equipment->setBorrowerUser($borrowerUser);

            if ($equipment instanceof Yumi) {
                $equipment->setMaterial($form->get('yumi_form')->get('material')->getData());
            }

            if ($equipment instanceof Glove) {
                $equipment->setNbFingers($form->get('glove_form')->get('nb_fingers')->getData());
            }

            $em->persist($equipment);
            $em->flush();

            $this->addFlash('success', ucfirst($equipment->getTypeName()).' ajouté !');

            return $this->redirectToRoute('equipment.index');
        }

        return $this->render('equipment/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/equipment/{id}/edit', name: 'equipment.edit', requirements: ['id' => '\d+'])]
    public function edit(Equipment $equipment, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EquipmentFormType::class, $equipment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Équipement modifié.');

            return $this->redirectToRoute('equipment.index');
        }

        return $this->render('equipment/edit.html.twig', [
            'equipments' => $equipment,
            'form' => $form,
        ]);
    }
}
