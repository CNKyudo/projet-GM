<?php

namespace App\Controller;

use App\Entity\Club;
use App\Form\ClubType;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/club')]
class ClubController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;

    #[Route('/', name: 'club_index', methods: ['GET'])]
    public function index(ClubRepository $clubRepository, Request $request, PaginatorInterface $paginator): Response
    {
        $qb = $clubRepository->createQueryBuilder('c')->orderBy('c.id', 'DESC');

        $clubs = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE,
        );

        return $this->render('club/index.html.twig', [
            'clubs' => $clubs,
        ]);
    }

    #[Route('/new', name: 'club_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $club = new Club();
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($club);
            $em->flush();

            $this->addFlash('success', 'Club créé.');

            return $this->redirectToRoute('club_index');
        }

        return $this->render('club/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'club_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Club $club): Response
    {
        return $this->render('club/show.html.twig', [
            'club' => $club,
        ]);
    }

    #[Route('/{id}/edit', name: 'club_edit', methods: ['GET','POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Club $club, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Club mis à jour.');

            return $this->redirectToRoute('club_index');
        }

        return $this->render('club/edit.html.twig', [
            'club' => $club,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'club_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Club $club, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$club->getId(), $request->request->get('_token'))) {
            $em->remove($club);
            $em->flush();
            $this->addFlash('success', 'Club supprimé.');
        }

        return $this->redirectToRoute('club_index');
    }
}
