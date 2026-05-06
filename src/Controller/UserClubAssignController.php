<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Club;
use App\Entity\User;
use App\Enum\UserRole;
use App\Form\UserClubAssignType;
use App\Security\Voter\UserPermissionVoter;
use App\Service\ClubRoleManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserClubAssignController extends AbstractController
{
    public function __construct(
        private readonly ClubRoleManager $clubRoleManager,
    ) {
    }

    #[Route('/user/{id}/club', name: 'user_assign_club', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(UserPermissionVoter::ASSIGN_USER_TO_ANY_CLUB)]
    public function assignClub(Request $request, User $targetUser, EntityManagerInterface $entityManager): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $prevClubWhichImPresidentOf = $targetUser->getClubWhichImPresidentOf();
        $prevClubWhereImEquipmentManager   = $targetUser->getClubWhereImEquipmentManager();

        $form = $this->createForm(UserClubAssignType::class, $targetUser, [
            'current_user' => $currentUser,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // For inverse-side OneToOne fields, Symfony won't call the setter when null is submitted
            // so we must explicitly clear them if the submitted value is null
            $submittedPresidentClub    = $form->get('clubWhichImPresidentOf')->getData();
            $submittedEquipmentManagerClub = $form->get('clubWhereImEquipmentManager')->getData();

            if (null === $submittedPresidentClub && $prevClubWhichImPresidentOf instanceof Club) {
                $targetUser->setClubWhichImPresidentOf(null);
            }

            if (null === $submittedEquipmentManagerClub && $prevClubWhereImEquipmentManager instanceof Club) {
                $targetUser->setClubWhereImEquipmentManager(null);
            }

            $newClubWhichImPresidentOf = $targetUser->getClubWhichImPresidentOf();
            $newClubWhereImEquipmentManagerOf   = $targetUser->getClubWhereImEquipmentManager();

            if ($newClubWhichImPresidentOf instanceof Club) {
                $previousPresidentOfClub = $entityManager
                    ->createQuery('SELECT u FROM App\Entity\User u JOIN u.clubWhichImPresidentOf c WHERE c.id = :id')
                    ->setParameter('id', $newClubWhichImPresidentOf->getId())
                    ->getOneOrNullResult();
            } else {
                $previousPresidentOfClub = $prevClubWhichImPresidentOf instanceof Club ? $targetUser : null;
            }

            $newPresidentOfClub      = $newClubWhichImPresidentOf instanceof Club ? $targetUser : null;

            if ($newClubWhereImEquipmentManagerOf instanceof Club) {
                $previousManagerOfClub = $entityManager
                    ->createQuery('SELECT u FROM App\Entity\User u JOIN u.clubWhereImEquipmentManager c WHERE c.id = :id')
                    ->setParameter('id', $newClubWhereImEquipmentManagerOf->getId())
                    ->getOneOrNullResult();
            } else {
                $previousManagerOfClub = $prevClubWhereImEquipmentManager instanceof Club ? $targetUser : null;
            }

            $newManagerOfClub      = $newClubWhereImEquipmentManagerOf instanceof Club ? $targetUser : null;

            $this->clubRoleManager->syncClubRoles($previousPresidentOfClub, $newPresidentOfClub, $previousManagerOfClub, $newManagerOfClub);

            // Force owning-side update regardless of what handleRequest() did to mark the fields as dirty so Doctrine flushes them
            if ($newClubWhichImPresidentOf instanceof Club) {
                $newClubWhichImPresidentOf->setPresident($targetUser);
            }

            if ($newClubWhereImEquipmentManagerOf instanceof Club) {
                $newClubWhereImEquipmentManagerOf->setEquipmentManager($targetUser);
            }

            // Clear old clubs' owning side
            if ($prevClubWhichImPresidentOf instanceof Club) {
                $prevClubWhichImPresidentOf->setPresident(null);
            }

            if ($prevClubWhereImEquipmentManager instanceof Club) {
                $prevClubWhereImEquipmentManager->setEquipmentManager(null);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Club mis à jour pour cet utilisateur.');

            return $this->redirectToRoute('user_index');
        }

        return $this->render('user/assign_club.html.twig', [
            'targetUser' => $targetUser,
            'form'       => $form,
            'roleLabels' => UserRole::labelsFromStrings($targetUser->getRoles()),
        ]);
    }
}
