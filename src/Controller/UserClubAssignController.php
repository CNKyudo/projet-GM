<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Club;
use App\Entity\User;
use App\Enum\UserRole;
use App\Form\UserClubAssignType;
use App\Security\Voter\UserPermissionVoter;
use App\Service\ClubRoleManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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
            $uow = $entityManager->getUnitOfWork();

            $newClubWhichImPresidentOf = $targetUser->getClubWhichImPresidentOf();
            $newClubWhereImEquipmentManagerOf   = $targetUser->getClubWhereImEquipmentManager();

            if (null !== $newClubWhichImPresidentOf) {
                $previousPresidentOfClub = $this->resolvePreviousPresident($uow->getOriginalEntityData(...), $newClubWhichImPresidentOf, $targetUser);
            } else {
                $previousPresidentOfClub = $targetUser;
            }
            $newPresidentOfClub      = $newClubWhichImPresidentOf instanceof Club ? $targetUser : null;

            if (null !== $newClubWhereImEquipmentManagerOf) {
                $previousManagerOfClub = $this->resolvePreviousManager($uow->getOriginalEntityData(...), $newClubWhereImEquipmentManagerOf, $targetUser);
            } else {
                $previousManagerOfClub = $targetUser;
            }
            $newManagerOfClub      = $newClubWhereImEquipmentManagerOf instanceof Club ? $targetUser : null;

            dump($previousPresidentOfClub, $newPresidentOfClub, $previousManagerOfClub, $newManagerOfClub);

            try {
                $this->clubRoleManager->syncClubRoles($previousPresidentOfClub, $newPresidentOfClub, $previousManagerOfClub, $newManagerOfClub);
                dd($targetUser);
                $entityManager->flush();

                $this->addFlash('success', 'Club mis à jour pour cet utilisateur.');

                return $this->redirectToRoute('user_index');
            } catch (UniqueConstraintViolationException) {
                $form->addError(new FormError('Ce club a déjà un président ou un gestionnaire matériel assigné.'));
            }
        }

        return $this->render('user/assign_club.html.twig', [
            'targetUser' => $targetUser,
            'form'       => $form,
            'roleLabels' => UserRole::labelsFromStrings($targetUser->getRoles()),
        ]);
    }

    /**
     * Détermine l'ancien président dont le rôle doit être révoqué.
     * Après handleRequest(), le nouveau club a déjà son president remplacé par le setter en cascade.
     * On utilise UnitOfWork::getOriginalEntityData() pour retrouver l'ancienne valeur.
     *
     * @param callable(Club):array|null $originalData
     */
    private function resolvePreviousPresident(callable $originalData, ?Club $club, User $targetUser): ?User
    {
        if ($club instanceof Club) {
            $original = $originalData($club);
            $oldPresident = $original['president'] ?? null;

            return $oldPresident instanceof User ? $oldPresident : null;
        }

        return null;
    }

    /**
     * @param callable(Club):array|null $originalData
     */
    private function resolvePreviousManager(callable $originalData, ?Club $club, User $targetUser): ?User
    {
        if ($club instanceof Club) {
            $original = $originalData($club);
            $oldManager = $original['equipmentManager'] ?? null;

            return $oldManager instanceof User ? $oldManager : null;
        }

        return null;
    }
}
