<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Club;
use App\Entity\ClubMember;
use App\Entity\Equipment;
use App\Entity\User;
use App\Enum\EquipmentLevel;
use App\Security\UserPermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class UserPermissionVoter extends Voter
{
    // Gestion des utilisateurs
    public const string ACCESS_USER_MANAGEMENT = 'ACCESS_USER_MANAGEMENT';

    public const string EDIT_OWN_ACCOUNT_INFORMATION = 'EDIT_OWN_ACCOUNT_INFORMATION';

    public const string ASSIGN_USER_TO_ANY_CLUB = 'ASSIGN_USER_TO_ANY_CLUB';

    public const string ASSIGN_USER_TO_OWN_CLUB = 'ASSIGN_USER_TO_OWN_CLUB';

    // Gestion des clubs (sans sujet = création/transfert, avec sujet Club = edit/delete)
    public const string CREATE_CLUB = 'CREATE_CLUB';

    public const string EDIT_CLUB = 'EDIT_CLUB';

    public const string DELETE_CLUB = 'DELETE_CLUB';

    public const string TRANSFER_CLUB_PRESIDENCY = 'TRANSFER_CLUB_PRESIDENCY';

    // Gestion des adresses
    public const string CREATE_ADDRESS = 'CREATE_ADDRESS';

    public const string EDIT_ADDRESS = 'EDIT_ADDRESS';

    public const string DELETE_ADDRESS = 'DELETE_ADDRESS';

    // Gestion des équipements (sans sujet = création, avec sujet Equipment = view/edit/borrow)
    public const string BROWSE_ALL_EQUIPMENT = 'BROWSE_ALL_EQUIPMENT';

    public const string CREATE_NATIONAL_EQUIPMENT = 'CREATE_NATIONAL_EQUIPMENT';

    public const string CREATE_REGIONAL_EQUIPMENT = 'CREATE_REGIONAL_EQUIPMENT';

    public const string CREATE_OWN_CLUB_EQUIPMENT = 'CREATE_OWN_CLUB_EQUIPMENT';

    public const string CREATE_EQUIPMENT_FOR_OTHER_CLUB = 'CREATE_EQUIPMENT_FOR_OTHER_CLUB';

    public const string VIEW_EQUIPMENT = 'VIEW_EQUIPMENT';

    public const string EDIT_EQUIPMENT = 'EDIT_EQUIPMENT';

    public const string BORROW_EQUIPMENT = 'BORROW_EQUIPMENT';

    public const string SET_ANOTHER_BORROWER_FOR_EQUIPMENT = 'SET_ANOTHER_BORROWER_FOR_EQUIPMENT';

    public const string CREATE_QRCODE = 'CREATE_QRCODE';

    public const string EDIT_QRCODE = 'EDIT_QRCODE';

    public const string DELETE_QRCODE = 'DELETE_QRCODE';

    public const string VIEW_QRCODE = 'VIEW_QRCODE';

    // Gestion des membres de club
    public const string CREATE_CLUB_MEMBER = 'CREATE_CLUB_MEMBER';

    public const string EDIT_CLUB_MEMBER = 'EDIT_CLUB_MEMBER';

    public const string DELETE_CLUB_MEMBER = 'DELETE_CLUB_MEMBER';

    /**
     * Attributs sans sujet : délégation directe au service via le rôle uniquement.
     *
     * @var array<string, string>
     */
    private const array ROLE_ONLY_ATTRIBUTES = [
        self::ACCESS_USER_MANAGEMENT => 'canAccessUserManagement',
        self::EDIT_OWN_ACCOUNT_INFORMATION => 'canEditOwnAccountInformation',
        self::ASSIGN_USER_TO_ANY_CLUB => 'canAssignUserToAnyClub',
        self::ASSIGN_USER_TO_OWN_CLUB => 'canAssignUserToOwnClub',
        self::CREATE_CLUB => 'canCreateClub',
        self::TRANSFER_CLUB_PRESIDENCY => 'canTransferClubPresidency',
        self::CREATE_ADDRESS => 'canCreateAddress',
        self::EDIT_ADDRESS => 'canEditAddress',
        self::DELETE_ADDRESS => 'canDeleteAddress',
        self::BROWSE_ALL_EQUIPMENT => 'canBrowseAllEquipment',
        self::CREATE_NATIONAL_EQUIPMENT => 'canCreateNationalEquipment',
        self::CREATE_REGIONAL_EQUIPMENT => 'canCreateRegionalEquipment',
        self::CREATE_OWN_CLUB_EQUIPMENT => 'canCreateOwnClubEquipment',
        self::CREATE_EQUIPMENT_FOR_OTHER_CLUB => 'canCreateEquipmentForOtherClub',
        self::CREATE_QRCODE => 'canCreateQRCode',
        self::EDIT_QRCODE => 'canEditQRCode',
        self::DELETE_QRCODE => 'canDeleteQRCode',
        self::VIEW_QRCODE => 'canViewQRCode',
    ];

    /**
     * Attributs avec sujet Equipment : la logique "own/other/regional/national" est résolue ici.
     */
    private const array EQUIPMENT_SUBJECT_ATTRIBUTES = [
        self::VIEW_EQUIPMENT,
        self::EDIT_EQUIPMENT,
        self::BORROW_EQUIPMENT,
        self::SET_ANOTHER_BORROWER_FOR_EQUIPMENT,
    ];

    /**
     * Attributs avec sujet Club : la logique "own vs other" est résolue ici.
     */
    private const array CLUB_SUBJECT_ATTRIBUTES = [
        self::EDIT_CLUB,
        self::DELETE_CLUB,
    ];

    /**
     * Attributs avec sujet ClubMember : logique "own club" résolue ici.
     */
    private const array CLUB_MEMBER_SUBJECT_ATTRIBUTES = [
        self::CREATE_CLUB_MEMBER,
        self::EDIT_CLUB_MEMBER,
        self::DELETE_CLUB_MEMBER,
    ];

    public function __construct(
        private readonly UserPermissionService $userPermissionService,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (\array_key_exists($attribute, self::ROLE_ONLY_ATTRIBUTES)) {
            return true;
        }

        if (\in_array($attribute, self::EQUIPMENT_SUBJECT_ATTRIBUTES, true)) {
            return $subject instanceof Equipment || null === $subject;
        }

        if (\in_array($attribute, self::CLUB_SUBJECT_ATTRIBUTES, true)) {
            return $subject instanceof Club || null === $subject;
        }

        if (\in_array($attribute, self::CLUB_MEMBER_SUBJECT_ATTRIBUTES, true)) {
            return $subject instanceof ClubMember || $subject instanceof Club || null === $subject;
        }

        return false;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Attributs sans sujet : délégation directe au service
        if (\array_key_exists($attribute, self::ROLE_ONLY_ATTRIBUTES)) {
            $method = self::ROLE_ONLY_ATTRIBUTES[$attribute];
            if (!\method_exists($this->userPermissionService, $method)) {
                return false;
            }

            return $this->userPermissionService->{$method}($user);
        }

        // Attributs avec sujet Equipment
        if (\in_array($attribute, self::EQUIPMENT_SUBJECT_ATTRIBUTES, true)) {
            return $this->voteOnEquipmentAttribute($attribute, $subject, $user);
        }

        // Attributs avec sujet Club
        if (\in_array($attribute, self::CLUB_SUBJECT_ATTRIBUTES, true)) {
            return $this->voteOnClubAttribute($attribute, $subject, $user);
        }

        // Attributs avec sujet ClubMember
        if (\in_array($attribute, self::CLUB_MEMBER_SUBJECT_ATTRIBUTES, true)) {
            return $this->voteOnClubMemberAttribute($attribute, $subject, $user);
        }

        return false;
    }

    private function voteOnEquipmentAttribute(string $attribute, mixed $subject, User $user): bool
    {
        // Sans sujet : vérifie uniquement si le rôle permet l'action sur son propre club
        if (!$subject instanceof Equipment) {
            return match ($attribute) {
                self::VIEW_EQUIPMENT => $this->userPermissionService->canViewOwnClubEquipment($user),
                self::EDIT_EQUIPMENT => $this->userPermissionService->canEditOwnClubEquipment($user),
                self::BORROW_EQUIPMENT => $this->userPermissionService->canBorrowOwnClubEquipment($user),
                self::SET_ANOTHER_BORROWER_FOR_EQUIPMENT => $this->userPermissionService->canSetAnotherBorrowerForOwnClubEquipment($user),
                default => false,
            };
        }

        return match ($subject->getEquipmentLevel()) {
            EquipmentLevel::NATIONAL => $this->voteOnNationalEquipmentAttribute($attribute, $subject, $user),
            EquipmentLevel::REGIONAL => $this->voteOnRegionalEquipmentAttribute($attribute, $subject, $user),
            EquipmentLevel::CLUB     => $this->voteOnClubEquipmentAttribute($attribute, $subject, $user),
        };
    }

    private function voteOnClubEquipmentAttribute(string $attribute, Equipment $equipment, User $user): bool
    {
        $ownerClub = $equipment->getOwnerClub();
        $userPresidentClub = $user->getClubWhichImPresidentOf();
        $userManagerClub = $user->getClubWhereImEquipmentManager();

        $isOwnClub = ($ownerClub instanceof Club) && (
            ($userPresidentClub instanceof Club && $ownerClub->getId() === $userPresidentClub->getId())
            || ($userManagerClub instanceof Club && $ownerClub->getId() === $userManagerClub->getId())
            || $user->getMemberOfClubs()->contains($ownerClub)
        );

        return match ($attribute) {
            self::VIEW_EQUIPMENT => $isOwnClub
                ? $this->userPermissionService->canViewOwnClubEquipment($user)
                : $this->userPermissionService->canViewOtherClubEquipment($user, $equipment),
            self::EDIT_EQUIPMENT => $isOwnClub
                ? $this->userPermissionService->canEditOwnClubEquipment($user)
                : $this->userPermissionService->canEditEquipmentFromOtherClub($user),
            self::BORROW_EQUIPMENT => $isOwnClub
                ? $this->userPermissionService->canBorrowOwnClubEquipment($user)
                : $this->userPermissionService->canBorrowEquipmentFromOtherClub($user),
            self::SET_ANOTHER_BORROWER_FOR_EQUIPMENT => $isOwnClub
                ? $this->userPermissionService->canSetAnotherBorrowerForOwnClubEquipment($user)
                : $this->userPermissionService->canSetAnotherBorrowerForOtherClubEquipment($user),
            default => false,
        };
    }

    private function voteOnRegionalEquipmentAttribute(string $attribute, Equipment $equipment, User $user): bool
    {
        return match ($attribute) {
            self::VIEW_EQUIPMENT => $this->userPermissionService->canViewRegionalEquipment($user, $equipment),
            self::EDIT_EQUIPMENT => $this->userPermissionService->canEditRegionalEquipment($user, $equipment),
            self::BORROW_EQUIPMENT,
            self::SET_ANOTHER_BORROWER_FOR_EQUIPMENT => $this->userPermissionService->canBorrowRegionalOrNationalEquipment($user),
            default => false,
        };
    }

    private function voteOnNationalEquipmentAttribute(string $attribute, Equipment $equipment, User $user): bool
    {
        return match ($attribute) {
            self::VIEW_EQUIPMENT => $this->userPermissionService->canViewNationalEquipment($user, $equipment),
            self::EDIT_EQUIPMENT => $this->userPermissionService->canEditNationalEquipment($user),
            self::BORROW_EQUIPMENT,
            self::SET_ANOTHER_BORROWER_FOR_EQUIPMENT => $this->userPermissionService->canBorrowRegionalOrNationalEquipment($user),
            default => false,
        };
    }

    private function voteOnClubAttribute(string $attribute, mixed $subject, User $user): bool
    {
        // Sans sujet : vérification basée sur le rôle uniquement
        if (!$subject instanceof Club) {
            return match ($attribute) {
                self::EDIT_CLUB => $this->userPermissionService->canEditClub($user),
                self::DELETE_CLUB => $this->userPermissionService->canDeleteClub($user),
                default => false,
            };
        }

        $userClub = $user->getClubWhichImPresidentOf();
        $isOwnClub = $userClub instanceof Club && $subject->getId() === $userClub->getId();

        return match ($attribute) {
            // Un président peut transférer/éditer son propre club, les managers peuvent tout éditer
            self::EDIT_CLUB => $isOwnClub
                ? $this->userPermissionService->canTransferClubPresidency($user)
                : $this->userPermissionService->canEditClub($user),
            self::DELETE_CLUB => $this->userPermissionService->canDeleteClub($user),
            default => false,
        };
    }

    /**
     * Vote sur les actions ClubMember.
     *
     * Règles :
     *  - CREATE : Président ou Manager du club concerné, ou ADMIN
     *  - EDIT   : Président/Manager du club OU User lié au ClubMember OU ADMIN
     *  - DELETE : Président/Manager du club OU ADMIN
     *
     * @param Club|ClubMember|null $subject
     */
    private function voteOnClubMemberAttribute(string $attribute, mixed $subject, User $user): bool
    {
        $isAdmin = $this->userPermissionService->canAccessUserManagement($user);

        if ($isAdmin) {
            return true;
        }

        // Pour CREATE, le sujet peut être un Club (on vérifie si l'user est président/manager de ce club)
        if (self::CREATE_CLUB_MEMBER === $attribute) {
            $club = $subject instanceof Club ? $subject : null;

            return $this->isPresidentOrManagerOf($user, $club);
        }

        // Pour EDIT et DELETE, le sujet est un ClubMember
        if (!$subject instanceof ClubMember) {
            return false;
        }

        $club = $subject->getClub();
        $isPresidentOrManager = $this->isPresidentOrManagerOf($user, $club);

        if (self::EDIT_CLUB_MEMBER === $attribute) {
            // Le User lié au ClubMember peut aussi modifier son propre profil
            $isLinkedUser = $subject->getUser() instanceof User && $subject->getUser()->getId() === $user->getId();

            return $isPresidentOrManager || $isLinkedUser;
        }

        // DELETE : seulement président/manager
        return $isPresidentOrManager;
    }

    private function isPresidentOrManagerOf(User $user, ?Club $club): bool
    {
        if (!$club instanceof Club) {
            return false;
        }

        $presidentClub = $user->getClubWhichImPresidentOf();
        $managerClub   = $user->getClubWhereImEquipmentManager();

        return ($presidentClub instanceof Club && $presidentClub->getId() === $club->getId())
            || ($managerClub instanceof Club && $managerClub->getId() === $club->getId());
    }
}
