<?php

namespace App\Controller;

use App\Entity\Equipment;
use App\Entity\Glove;
use App\Entity\Yumi;
use App\Enum\EquipmentType;
use App\Repository\EquipmentRepository;
use App\Form\EquipmentFormType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EquipmentController extends AbstractController
{
    #[Route('/equipment', name: 'equipment.index')]
    public function index(Request $request, EquipmentRepository $repository): Response
    {
        $equipments = $repository->findAll();
        // dd($equipments);
        return $this->render('equipment/index.html.twig', [
            'equipments' => $equipments,
        ]);
    }

    #[Route('/equipment/{id}', name: 'equipment.show', requirements: ['id'=>'\d+'] )]
    public function show(Equipment $equipment): Response
    {
        return $this->render('equipment/show.html.twig', [
            'equipment' => $equipment,
        ]);  
    }

    #[Route('/equipment/create', name: 'equipment.create')]
    public function create(Request $request, EntityManagerInterface $em)
    {
        $form = $this->createForm(EquipmentFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $type = $form->get('equipment_type')->getData();
            $ownerClub = $form->get('owner_club')->getData();
            $borrowerClub = $form->get('borrower_club')->getData();
            $borrowerUser = $form->get('borrower_user')->getData();

            $equipment = match($type) {
                EquipmentType::YUMI => new Yumi(),
                EquipmentType::GLOVE => new Glove(),
            };

            $equipment->setOwnerClub($ownerClub);
            $equipment->setBorrowerClub($borrowerClub);
            $equipment->setBorrowerUser($borrowerUser);

            $em->persist($equipment);
            $em->flush();

            $this->addFlash('success', ucfirst($equipment->getTypeName()) . ' ajouté !');
            return $this->redirectToRoute('equipment.index');
        }

        return $this->render('equipment/create.html.twig', [
            'form' => $form->createView()
        ]);
    }
 
    #[Route('/equipment/{id}/edit', name: 'equipment.edit', requirements: ['id'=>'\d+'] )]
    public function edit(Equipment $equipment, Request $request, EntityManagerInterface $em)
    {
        $form = $this->createForm(EquipmentFormType::class, $equipment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid())
        {
            $em->flush();
            $this->addFlash('success','Équipement modifié.');
            return $this->redirectToRoute('equipment.index');
        }
        return $this->render('equipment/edit.html.twig', [
            'equipments' => $equipment,
            'form' => $form
        ]);
    }
}
