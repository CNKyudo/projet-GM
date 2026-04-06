<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Security\UserPermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class UserPermissionVoter extends Voter
{
    public const ACCESS_USER_MANAGEMENT = 'ACCESS_USER_MANAGEMENT';
    public const EDIT_USER_ROLES = 'EDIT_USER_ROLES';
    public const CREATE_NATIONAL_EQUIPMENT = 'CREATE_NATIONAL_EQUIPMENT';
    public const CREATE_REGIONAL_EQUIPMENT = 'CREATE_REGIONAL_EQUIPMENT';
    public const CREATE_OWN_CLUB_EQUIPMENT = 'CREATE_OWN_CLUB_EQUIPMENT';
    public const CREATE_EQUIPMENT_FOR_OTHER_CLUB = 'CREATE_EQUIPMENT_FOR_OTHER_CLUB';
    public const EDIT_NATIONAL_EQUIPMENT = 'EDIT_NATIONAL_EQUIPMENT';
    public const EDIT_REGIONAL_EQUIPMENT = 'EDIT_REGIONAL_EQUIPMENT';
    public const EDIT_OWN_CLUB_EQUIPMENT = 'EDIT_OWN_CLUB_EQUIPMENT';
    public const EDIT_EQUIPMENT_FROM_OTHER_CLUB = 'EDIT_EQUIPMENT_FROM_OTHER_CLUB';
    public const VIEW_OWN_CLUB_EQUIPMENT = 'VIEW_OWN_CLUB_EQUIPMENT';
    public const VIEW_EQUIPMENT_FROM_OTHER_CLUB = 'VIEW_EQUIPMENT_FROM_OTHER_CLUB';
    public const CREATE_CLUB = 'CREATE_CLUB';
    public const TRANSFER_CLUB_PRESIDENCY = 'TRANSFER_CLUB_PRESIDENCY';
    public const APPOINT_CLUB_PRESIDENT = 'APPOINT_CLUB_PRESIDENT';
    public const ASSIGN_REGIONAL_ROLES = 'ASSIGN_REGIONAL_ROLES';
    public const ASSIGN_NATIONAL_ROLES = 'ASSIGN_NATIONAL_ROLES';
    public const ASSIGN_USER_TO_ANY_CLUB = 'ASSIGN_USER_TO_ANY_CLUB';
    public const ASSIGN_USER_TO_OWN_CLUB = 'ASSIGN_USER_TO_OWN_CLUB';
    public const BORROW_OWN_CLUB_EQUIPMENT = 'BORROW_OWN_CLUB_EQUIPMENT';
    public const BORROW_EQUIPMENT_FROM_OTHER_CLUB = 'BORROW_EQUIPMENT_FROM_OTHER_CLUB';
    public const SET_ANOTHER_BORROWER_FOR_OWN_CLUB_EQUIPMENT = 'SET_ANOTHER_BORROWER_FOR_OWN_CLUB_EQUIPMENT';
    public const SET_ANOTHER_BORROWER_FOR_OTHER_CLUB_EQUIPMENT = 'SET_ANOTHER_BORROWER_FOR_OTHER_CLUB_EQUIPMENT';
    public const EDIT_OWN_ACCOUNT_INFORMATION = 'EDIT_OWN_ACCOUNT_INFORMATION';
    public const CREATE_QRCODE = 'CREATE_QRCODE';
    public const EDIT_QRCODE = 'EDIT_QRCODE';
    public const DELETE_QRCODE = 'DELETE_QRCODE';
    public const VIEW_QRCODE = 'VIEW_QRCODE';

    /**
     * @var array<string, string>
     */
    private const METHOD_BY_ATTRIBUTE = [
        self::ACCESS_USER_MANAGEMENT => 'canAccessUserManagement',
        self::EDIT_USER_ROLES => 'canEditUserRoles',
        self::CREATE_NATIONAL_EQUIPMENT => 'canCreateNationalEquipment',
        self::CREATE_REGIONAL_EQUIPMENT => 'canCreateRegionalEquipment',
        self::CREATE_OWN_CLUB_EQUIPMENT => 'canCreateOwnClubEquipment',
        self::CREATE_EQUIPMENT_FOR_OTHER_CLUB => 'canCreateEquipmentForOtherClub',
        self::EDIT_NATIONAL_EQUIPMENT => 'canEditNationalEquipment',
        self::EDIT_REGIONAL_EQUIPMENT => 'canEditRegionalEquipment',
        self::EDIT_OWN_CLUB_EQUIPMENT => 'canEditOwnClubEquipment',
        self::EDIT_EQUIPMENT_FROM_OTHER_CLUB => 'canEditEquipmentFromOtherClub',
        self::VIEW_OWN_CLUB_EQUIPMENT => 'canViewOwnClubEquipment',
        self::VIEW_EQUIPMENT_FROM_OTHER_CLUB => 'canViewEquipmentFromOtherClub',
        self::CREATE_CLUB => 'canCreateClub',
        self::TRANSFER_CLUB_PRESIDENCY => 'canTransferClubPresidency',
        self::APPOINT_CLUB_PRESIDENT => 'canAppointClubPresident',
        self::ASSIGN_REGIONAL_ROLES => 'canAssignRegionalRoles',
        self::ASSIGN_NATIONAL_ROLES => 'canAssignNationalRoles',
        self::ASSIGN_USER_TO_ANY_CLUB => 'canAssignUserToAnyClub',
        self::ASSIGN_USER_TO_OWN_CLUB => 'canAssignUserToOwnClub',
        self::BORROW_OWN_CLUB_EQUIPMENT => 'canBorrowOwnClubEquipment',
        self::BORROW_EQUIPMENT_FROM_OTHER_CLUB => 'canBorrowEquipmentFromOtherClub',
        self::SET_ANOTHER_BORROWER_FOR_OWN_CLUB_EQUIPMENT => 'canSetAnotherBorrowerForOwnClubEquipment',
        self::SET_ANOTHER_BORROWER_FOR_OTHER_CLUB_EQUIPMENT => 'canSetAnotherBorrowerForOtherClubEquipment',
        self::EDIT_OWN_ACCOUNT_INFORMATION => 'canEditOwnAccountInformation',
        self::CREATE_QRCODE => 'canCreateQRCode',
        self::EDIT_QRCODE => 'canEditQRCode',
        self::DELETE_QRCODE => 'canDeleteQRCode',
        self::VIEW_QRCODE => 'canViewQRCode',
    ];

    public function __construct(
        private readonly UserPermissionService $userPermissionService,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \array_key_exists($attribute, self::METHOD_BY_ATTRIBUTE);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $method = self::METHOD_BY_ATTRIBUTE[$attribute] ?? null;
        if (null === $method || !\method_exists($this->userPermissionService, $method)) {
            return false;
        }

        return $this->userPermissionService->{$method}($user);
    }
}
