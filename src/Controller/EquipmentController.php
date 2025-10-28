<?php

namespace App\Controller;

use App\Entity\Equipment;
use App\Entity\Glove;
use App\Entity\Yumi;
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
    public function show(Request $request, int $id, EquipmentRepository $repository): Response
    {
        $equipment = $repository->find($id);
        return $this->render('equipment/show.html.twig', [
            'equipment' => $equipment,
        ]);
    }

    #[Route('/equipment/create_{type}', name: 'equipment.create')]
    public function create(Request $request, string $type, EntityManagerInterface $em)
    {
        $equipmentTypes = [
            'yumi'  => Yumi::class,
            'kake' => Glove::class,
        ];

        if (!isset($equipmentTypes[$type])) {
            throw new \LogicException('Invalid equipment type');
        }

        $class = $equipmentTypes[$type];
        $equipment = new $class();
        $form = $this->createForm(EquipmentFormType::class, $equipment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid())
        {
            $em->persist($equipment);
            $em->flush();
            $this->addFlash('success', ucfirst($type).' ajouté.');
            return $this->redirectToRoute('equipment.index');
        }
        return $this->render('equipment/create.html.twig', [
            'form' => $form,
            'type' => $type,
        ]);
    }

    #[Route('/equipment/{id}/edit', name: 'equipment.edit')]
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
