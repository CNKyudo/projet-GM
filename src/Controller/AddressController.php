<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Club;
use App\Form\AddressType;
use App\Repository\ClubRepository;
use App\Security\Voter\UserPermissionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AddressController extends AbstractController
{
    public function __construct(private readonly ClubRepository $clubRepository)
    {
    }

    #[Route('/address/new', name: 'address_new', methods: ['GET', 'POST'])]
    #[IsGranted(UserPermissionVoter::CREATE_ADDRESS)]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $address = new Address();
        $form = $this->createForm(AddressType::class, $address);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($address);
            $entityManager->flush();

            $this->addFlash('success', 'Adresse créée.');

            return $this->redirectToRoute('address_show', ['id' => $address->getId()]);
        }

        return $this->render('address/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/address/{id}', name: 'address_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(Address $address): Response
    {
        return $this->render('address/show.html.twig', [
            'address' => $address,
        ]);
    }

    #[Route('/address/{id}/edit', name: 'address_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(UserPermissionVoter::EDIT_ADDRESS)]
    public function edit(Request $request, Address $address, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AddressType::class, $address, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Adresse mise à jour.');

            return $this->redirectToRoute('address_show', ['id' => $address->getId()]);
        }

        return $this->render('address/edit.html.twig', [
            'address' => $address,
            'form' => $form,
        ]);
    }

    #[Route('/address/{id}', name: 'address_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(UserPermissionVoter::DELETE_ADDRESS)]
    public function delete(Request $request, Address $address, EntityManagerInterface $entityManager): Response
    {
        $token = (string) $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete'.$address->getId(), $token)) {
            $club = $this->clubRepository->findOneBy(['address' => $address]);

            if ($club instanceof Club) {
                $this->addFlash('error', 'Adresse déjà associée à un club ('.$club->getName()."). Changez l'adresse du club pour supprimer celle ci.");

                return $this->redirectToRoute('address_show', ['id' => $address->getId()]);
            }

            $entityManager->remove($address);
            $entityManager->flush();
            $this->addFlash('success', 'Adresse supprimée.');
        }

        return $this->redirectToRoute('app_home');
    }
}
