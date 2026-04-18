<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Region;
use App\Form\RegionType;
use App\Repository\RegionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Enum\UserRole;

#[IsGranted(UserRole::ADMIN->value)]
class RegionController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;

    public function __construct(private readonly RegionRepository $regionRepository, private readonly PaginatorInterface $paginator)
    {
    }

    #[Route('/region/', name: 'region_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $queryBuilder = $this->regionRepository->createQueryBuilder('r')
            ->leftJoin('r.federation', 'f')
            ->addSelect('f')
            ->orderBy('r.name', 'ASC');

        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE,
        );

        return $this->render('region/index.html.twig', [
            'regions' => $pagination,
        ]);
    }

    #[Route('/region/new', name: 'region_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $region = new Region();
        $form = $this->createForm(RegionType::class, $region);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($region);
            $entityManager->flush();

            $this->addFlash('success', 'Région créée.');

            return $this->redirectToRoute('region_index');
        }

        return $this->render('region/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/region/{id}', name: 'region_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Region $region): Response
    {
        return $this->render('region/show.html.twig', [
            'region' => $region,
        ]);
    }

    #[Route('/region/{id}/edit', name: 'region_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Region $region, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RegionType::class, $region);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Région mise à jour.');

            return $this->redirectToRoute('region_index');
        }

        return $this->render('region/edit.html.twig', [
            'region' => $region,
            'form' => $form,
        ]);
    }

    #[Route('/region/{id}', name: 'region_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Region $region, EntityManagerInterface $entityManager): Response
    {
        $token = (string) $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete'.$region->getId(), $token)) {
            $entityManager->remove($region);
            $entityManager->flush();
            $this->addFlash('success', 'Région supprimée.');
        }

        return $this->redirectToRoute('region_index');
    }
}
