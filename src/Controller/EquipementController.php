<?php

namespace App\Controller;

use App\Repository\EquipmentRepository;
use App\Form\EquipementFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EquipementController extends AbstractController
{
    #[Route('/equipement', name: 'app_equipement')]
    public function index(Request $request, EquipmentRepository $repository): Response
    {
        $equipements = $repository->findAll();
        // dd($equipements);
        return $this->render('equipement/index.html.twig', [
            'equipements' => $equipements,
        ]);
    }
}
