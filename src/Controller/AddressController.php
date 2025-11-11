<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Club;
use App\Form\AddressType;
use App\Repository\AddressRepository;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/address')]
class AddressController extends AbstractController
{
  #[Route('/', name: 'address_index', methods: ['GET'])]
  public function index(AddressRepository $addressRepository): Response
  {
    return $this->render('address/index.html.twig', [
      'addresses' => $addressRepository->findAll(),
    ]);
  }

  #[Route('/new', name: 'address_new', methods: ['GET', 'POST'])]
  public function new(Request $request, EntityManagerInterface $em): Response
  {
    $address = new Address();
    $form = $this->createForm(AddressType::class, $address);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $em->persist($address);
      $em->flush();

      $this->addFlash('success', 'Adresse créée.');

      $referer = $request->headers->get('referer');
      if ($referer) {
        return $this->redirect($referer);
      }

      return $this->redirectToRoute('address_index');
    }

    return $this->render('address/new.html.twig', [
      'form' => $form->createView(),
    ]);
  }

  #[Route('/{id}', name: 'address_show', methods: ['GET'], requirements: ['id' => '\d+'])]
  public function show(Address $address): Response
  {
    return $this->render('address/show.html.twig', [
      'address' => $address,
    ]);
  }

  #[Route('/{id}/edit', name: 'address_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
  public function edit(Request $request, Address $address, EntityManagerInterface $em): Response
  {
    $form = $this->createForm(AddressType::class, $address);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $em->flush();
      $this->addFlash('success', 'Adresse mise à jour.');

      return $this->redirectToRoute('address_index');
    }

    return $this->render('address/edit.html.twig', [
      'address' => $address,
      'form' => $form->createView(),
    ]);
  }

  #[Route('/{id}', name: 'address_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
  public function delete(Request $request, Address $address, EntityManagerInterface $em, ClubRepository $clubRepo): Response
  {
    if ($this->isCsrfTokenValid('delete' . $address->getId(), $request->request->get('_token'))) {
      $club = $clubRepo->findOneBy(['address' => $address]);

      if ($club instanceof Club) {
        $this->addFlash('error', 'Adresse déjà associée à un club (' . $club->getName() . '). Changez l\'adresse du club pour supprimer celle ci.');
        return $this->redirectToRoute('address_index');
      }

      $em->remove($address);
      $em->flush();
      $this->addFlash('success', 'Adresse supprimée.');
    }

    return $this->redirectToRoute('address_index');
  }
}
