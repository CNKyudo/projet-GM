<?php

namespace App\Controller;

use App\Entity\Equipment;
use App\Repository\EquipmentRepository;
use App\Form\EquipmentFormType;
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

    #[Route('/equipment/{id}', name: 'equipment.show')]
    public function show(Request $request, int $id, EquipmentRepository $repository): Response
    {
        $equipments = $repository->find($id);
        // dd($equipments);
        return $this->render('equipment/show.html.twig', [
            'equipments' => $equipments,
        ]);
    }

    #[Route('/equipment/{id}/edit', name: 'equipment.edit')]
    public function edit(Equipment $equipment, Request $request, EntityManagerInterface $em)
    {
        // dd($equipment);
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
