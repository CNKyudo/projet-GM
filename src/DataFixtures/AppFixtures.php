<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Club;
use App\Entity\ClubMember;
use App\Entity\Federation;
use App\Entity\Yugake;
use App\Entity\Region;
use App\Entity\User;
use App\Entity\Yumi;
use App\Enum\EquipmentLevel;
use App\Enum\UserRole;
use App\Enum\YumiLength;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée un jeu de données reproductible pour les tests fonctionnels et la démonstration.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * COMPTES DE TEST (mot de passe : "password" pour tous)
 * ─────────────────────────────────────────────────────────────────────────────
 * Ces constantes sont utilisées dans les tests fonctionnels — NE PAS MODIFIER.
 *
 *   user@kyudo-test.fr      → ROLE_USER (aucune appartenance)
 *   member@kyudo-test.fr    → ROLE_MEMBER (membre du Club A)
 *   president@kyudo-test.fr → ROLE_CLUB_PRESIDENT (président du Club A)
 *   mgr-club@kyudo-test.fr  → ROLE_EQUIPMENT_MANAGER_CLUB (gestionnaire matériel du Club A)
 *   mgr-ctk@kyudo-test.fr   → ROLE_EQUIPMENT_MANAGER_CTK (gestionnaire de Région A – Ile de France)
 *   mgr-cn@kyudo-test.fr    → ROLE_EQUIPMENT_MANAGER_CN
 *   admin@kyudo-test.fr     → ROLE_ADMIN
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DONNÉES RÉALISTES
 * ─────────────────────────────────────────────────────────────────────────────
 * Fédération :
 *   - Comité National de Kyudo (CNKyudo)
 *
 * Régions (CTK) :
 *   - Ile de France        (Région A — gérée par mgr-ctk@kyudo-test.fr)
 *   - Auvergne Rhone Alpe  (Région B)
 *   - Arc Atlantique       (Région C)
 *   - Nord & Est           (Région D)
 *   - Grand Sud            (Région E)
 *
 * Clubs (15) :
 *   Ile de France :
 *     - Kyudo Paris Marais      (Club A — présidé par president@kyudo-test.fr, géré par mgr-club@kyudo-test.fr)
 *     - Kyudo Vincennes          (Club C — même région que Club A, pour tester les restrictions régionales)
 *     - Ryushin Dojo Paris       (Club D)
 *   Auvergne Rhone Alpe :
 *     - Kyudo Lyon               (Club B — sans président)
 *     - Kyudo Grenoble           (Club E)
 *     - Dojo Zen Clermont        (Club F)
 *   Arc Atlantique :
 *     - Kyudo Rennes             (Club G)
 *     - Kyudo Lorient            (Club J)
 *     - Kyudo Nantes             (Club K)
 *   Nord & Est :
 *     - Kyudo Brest              (Club H)
 *     - Kyudo Strasbourg         (Club L)
 *     - Kyudo Lille              (Club M)
 *   Grand Sud :
 *     - Dojo Bretagne Quimper    (Club I)
 *     - Kyudo Marseille          (Club N)
 *     - Kyudo Toulouse           (Club O)
 *
 * Équipements (19) :
 *   Gants (Yugake) :
 *     - 3 × niveau CLUB (clubs A, B, C)
 *     - 1 × niveau CLUB avec emprunteur club (Club G → Rennes)
 *     - 1 × niveau REGIONAL (Région A) disponible
 *     - 1 × niveau REGIONAL (Région A) emprunté
 *     - 1 × niveau REGIONAL (Région C) disponible
 *     - 1 × niveau NATIONAL disponible
 *     - 1 × niveau NATIONAL emprunté
 *   Arcs (Yumi) :
 *     - 4 × niveau CLUB (clubs A, D, E, G)
 *     - 2 × niveau REGIONAL (régions B, C)
 *     - 2 × niveau NATIONAL
 *     - 1 × niveau CLUB avec emprunteur ClubMember (membre lié)
 */
class AppFixtures extends Fixture
{
    // ─── Constantes utilisées dans les tests fonctionnels ───────────────────
    public const USER_USER = 'user@kyudo-test.fr';

    public const USER_MEMBER = 'member@kyudo-test.fr';

    public const USER_PRESIDENT = 'president@kyudo-test.fr';

    public const USER_MANAGER_CLUB = 'mgr-club@kyudo-test.fr';

    public const USER_MANAGER_CTK = 'mgr-ctk@kyudo-test.fr';

    public const USER_MANAGER_CN = 'mgr-cn@kyudo-test.fr';

    public const USER_ADMIN = 'admin@kyudo-test.fr';

    public const CLUB_A = 'Kyudo Paris Marais';

    public const CLUB_B = 'Kyudo Lyon';

    public const CLUB_C = 'Kyudo Vincennes';

    public const CLUB_D = 'Ryushin Dojo Paris';

    public const CLUB_G = 'Kyudo Rennes';

    public const REGION_A = 'Ile de France';

    public const REGION_B = 'Auvergne Rhone Alpe';

    public const REGION_C = 'Arc Atlantique';

    public const REGION_D = 'Nord & Est';

    public const REGION_E = 'Grand Sud';

    public const FEDERATION_NAME = 'Comité National de Kyudo';

    public const PASSWORD = 'password';

    /** Email du ClubMember non-inscrit utilisé dans les tests fonctionnels. */
    public const CLUB_MEMBER_UNREGISTERED_EMAIL = 'non-inscrit.test@kyudo-test.fr';

    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $hashedPassword = $this->userPasswordHasher->hashPassword(new User(), self::PASSWORD);

        // ────────────────────────────────────────────────────────────────────
        // Fédération
        // ────────────────────────────────────────────────────────────────────
        $federation = new Federation()
            ->setName(self::FEDERATION_NAME)
            ->setEmail('contact@cnkyudo.fr');
        $manager->persist($federation);

        // ────────────────────────────────────────────────────────────────────
        // Régions (CTK)
        // ────────────────────────────────────────────────────────────────────
        $regionA = new Region()
            ->setFederation($federation)
            ->setName(self::REGION_A)
            ->setEmail('ctk.idf@cnkyudo.fr');
        $manager->persist($regionA);

        $regionB = new Region()
            ->setFederation($federation)
            ->setName(self::REGION_B)
            ->setEmail('ctk.aura@cnkyudo.fr');
        $manager->persist($regionB);

        $regionC = new Region()
            ->setFederation($federation)
            ->setName(self::REGION_C)
            ->setEmail('ctk.arc-atlantique@cnkyudo.fr');
        $manager->persist($regionC);

        $regionD = new Region()
            ->setFederation($federation)
            ->setName(self::REGION_D)
            ->setEmail('ctk.nord-est@cnkyudo.fr');
        $manager->persist($regionD);

        $regionE = new Region()
            ->setFederation($federation)
            ->setName(self::REGION_E)
            ->setEmail('ctk.grand-sud@cnkyudo.fr');
        $manager->persist($regionE);

        // ────────────────────────────────────────────────────────────────────
        // Comptes de test (requis par les tests fonctionnels)
        // ────────────────────────────────────────────────────────────────────
        /** @var array<string, array{roles: list<string>}> $testUsers */
        $testUsers = [
            self::USER_USER         => ['roles' => []],
            self::USER_MEMBER       => ['roles' => [UserRole::MEMBER->value]],
            self::USER_PRESIDENT    => ['roles' => [UserRole::CLUB_PRESIDENT->value]],
            self::USER_MANAGER_CLUB => ['roles' => [UserRole::EQUIPMENT_MANAGER_CLUB->value]],
            self::USER_MANAGER_CTK  => ['roles' => [UserRole::EQUIPMENT_MANAGER_CTK->value]],
            self::USER_MANAGER_CN   => ['roles' => [UserRole::EQUIPMENT_MANAGER_CN->value]],
            self::USER_ADMIN        => ['roles' => [UserRole::ADMIN->value]],
        ];

        /** @var array<string, User> $u */
        $u = [];
        foreach ($testUsers as $email => $data) {
            $user = new User()
                ->setEmail($email)
                ->setPassword($hashedPassword)
                ->setRoles($data['roles']);
            $manager->persist($user);
            $u[$email] = $user;
        }

        // manager_ctk gère la Région A (Ile de France)
        $u[self::USER_MANAGER_CTK]->addManagedRegion($regionA);

        // ────────────────────────────────────────────────────────────────────
        // Utilisateurs réalistes
        // ────────────────────────────────────────────────────────────────────

        // Gestionnaire CTK Auvergne Rhone Alpe
        $ctkAura = new User()
            ->setEmail('ctk.aura@cnkyudo.fr')
            ->setPassword($hashedPassword)
            ->setRoles([UserRole::EQUIPMENT_MANAGER_CTK->value]);
        $ctkAura->addManagedRegion($regionB);

        $manager->persist($ctkAura);

        // Gestionnaire CTK Arc Atlantique
        $ctkArcAtlantique = new User()
            ->setEmail('ctk.arc-atlantique@cnkyudo.fr')
            ->setPassword($hashedPassword)
            ->setRoles([UserRole::EQUIPMENT_MANAGER_CTK->value]);
        $ctkArcAtlantique->addManagedRegion($regionC);

        $manager->persist($ctkArcAtlantique);

        // Gestionnaire CTK Nord & Est
        $ctkNordEst = new User()
            ->setEmail('ctk.nord-est@cnkyudo.fr')
            ->setPassword($hashedPassword)
            ->setRoles([UserRole::EQUIPMENT_MANAGER_CTK->value]);
        $ctkNordEst->addManagedRegion($regionD);

        $manager->persist($ctkNordEst);

        // Gestionnaire CTK Grand Sud
        $ctkGrandSud = new User()
            ->setEmail('ctk.grand-sud@cnkyudo.fr')
            ->setPassword($hashedPassword)
            ->setRoles([UserRole::EQUIPMENT_MANAGER_CTK->value]);
        $ctkGrandSud->addManagedRegion($regionE);

        $manager->persist($ctkGrandSud);

        // Membres et présidents supplémentaires
        $presidentLyon = new User()
            ->setEmail('president.lyon@kyudo.fr')
            ->setPassword($hashedPassword)
            ->setRoles([UserRole::CLUB_PRESIDENT->value]);
        $manager->persist($presidentLyon);

        $presidentRennes = new User()
            ->setEmail('president.rennes@kyudo.fr')
            ->setPassword($hashedPassword)
            ->setRoles([UserRole::CLUB_PRESIDENT->value]);
        $manager->persist($presidentRennes);

        $managerLyon = new User()
            ->setEmail('manager.lyon@kyudo.fr')
            ->setPassword($hashedPassword)
            ->setRoles([UserRole::EQUIPMENT_MANAGER_CLUB->value]);
        $manager->persist($managerLyon);

        $membre1 = new User()
            ->setEmail('alice.martin@kyudo.fr')
            ->setPassword($hashedPassword)
            ->setRoles([UserRole::MEMBER->value]);
        $manager->persist($membre1);

        $membre2 = new User()
            ->setEmail('benoit.dupont@kyudo.fr')
            ->setPassword($hashedPassword)
            ->setRoles([UserRole::MEMBER->value]);
        $manager->persist($membre2);

        $membre3 = new User()
            ->setEmail('claire.robert@kyudo.fr')
            ->setPassword($hashedPassword)
            ->setRoles([UserRole::MEMBER->value]);
        $manager->persist($membre3);

        $membre4 = new User()
            ->setEmail('david.leclerc@kyudo.fr')
            ->setPassword($hashedPassword)
            ->setRoles([UserRole::MEMBER->value]);
        $manager->persist($membre4);

        for ($i = 0; $i < 25; ++$i) {
            $membre = new User()
            ->setEmail('user'.$i.'@kyudo.fr')
            ->setPassword($hashedPassword)
            ->setRoles([UserRole::MEMBER->value]);
            $manager->persist($membre);
        }

        // ────────────────────────────────────────────────────────────────────
        // Clubs
        // ────────────────────────────────────────────────────────────────────

        // — Ile de France —
        $clubA = new Club()                                       // Club A (tests)
            ->setName(self::CLUB_A)
            ->setEmail('contact@kyudo-paris-marais.fr')
            ->setPresident($u[self::USER_PRESIDENT])
            ->setEquipmentManager($u[self::USER_MANAGER_CLUB])
            ->setRegion($regionA);
        $clubA->addMember($u[self::USER_MEMBER]);
        $clubA->addMember($membre1);

        $manager->persist($clubA);

        $clubC = new Club()                                       // Club C (même région que A)
            ->setName(self::CLUB_C)
            ->setEmail('contact@kyudo-vincennes.fr')
            ->setRegion($regionA);
        $clubC->addMember($membre2);

        $manager->persist($clubC);

        $clubD = new Club()
            ->setName(self::CLUB_D)
            ->setEmail('contact@ryushin-paris.fr')
            ->setRegion($regionA);
        $manager->persist($clubD);

        // — Auvergne Rhone Alpe —
        $clubB = new Club()                                       // Club B (tests)
            ->setName(self::CLUB_B)
            ->setEmail('contact@kyudo-lyon.fr')
            ->setPresident($presidentLyon)
            ->setEquipmentManager($managerLyon)
            ->setRegion($regionB);
        $clubB->addMember($membre3);

        $manager->persist($clubB);

        $clubE = new Club()
            ->setName('Kyudo Grenoble')
            ->setEmail('contact@kyudo-grenoble.fr')
            ->setRegion($regionB);
        $manager->persist($clubE);

        $clubF = new Club()
            ->setName('Dojo Zen Clermont')
            ->setEmail('contact@dojo-clermont.fr')
            ->setRegion($regionB);
        $manager->persist($clubF);

        // — Arc Atlantique —
        $clubG = new Club()                                       // Club G (tests)
            ->setName(self::CLUB_G)
            ->setEmail('contact@kyudo-rennes.fr')
            ->setPresident($presidentRennes)
            ->setRegion($regionC);
        $clubG->addMember($membre4);

        $manager->persist($clubG);

        $clubJ = new Club()
            ->setName('Kyudo Lorient')
            ->setEmail('contact@kyudo-lorient.fr')
            ->setRegion($regionC);
        $manager->persist($clubJ);

        $clubK = new Club()
            ->setName('Kyudo Nantes')
            ->setEmail('contact@kyudo-nantes.fr')
            ->setRegion($regionC);
        $manager->persist($clubK);

        // — Nord & Est —
        $clubH = new Club()
            ->setName('Kyudo Brest')
            ->setEmail('contact@kyudo-brest.fr')
            ->setRegion($regionD);
        $manager->persist($clubH);

        $clubL = new Club()
            ->setName('Kyudo Strasbourg')
            ->setEmail('contact@kyudo-strasbourg.fr')
            ->setRegion($regionD);
        $manager->persist($clubL);

        $clubM = new Club()
            ->setName('Kyudo Lille')
            ->setEmail('contact@kyudo-lille.fr')
            ->setRegion($regionD);
        $manager->persist($clubM);

        // — Grand Sud —
        $clubI = new Club()
            ->setName('Dojo Bretagne Quimper')
            ->setEmail('contact@dojo-quimper.fr')
            ->setRegion($regionE);
        $manager->persist($clubI);

        $clubN = new Club()
            ->setName('Kyudo Marseille')
            ->setEmail('contact@kyudo-marseille.fr')
            ->setRegion($regionE);
        $manager->persist($clubN);

        $clubO = new Club()
            ->setName('Kyudo Toulouse')
            ->setEmail('contact@kyudo-toulouse.fr')
            ->setRegion($regionE);
        $manager->persist($clubO);

        // ────────────────────────────────────────────────────────────────────
        // ClubMembers
        // ────────────────────────────────────────────────────────────────────

        // Club A — membre non-inscrit utilisé dans les tests fonctionnels
        $unregisteredMember = new ClubMember()
            ->setFirstName('NonInscrit')
            ->setLastName('Test')
            ->setEmail(self::CLUB_MEMBER_UNREGISTERED_EMAIL)
            ->setClub($clubA);
        $manager->persist($unregisteredMember);

        // Club A — membre lié à l'utilisateur "member@kyudo-test.fr"
        $memberLinked = new ClubMember()
            ->setFirstName('Jean')
            ->setLastName('Dupont')
            ->setEmail(self::USER_MEMBER)
            ->setUser($u[self::USER_MEMBER])
            ->setClub($clubA);
        $manager->persist($memberLinked);

        // Club B (Lyon) — deux membres sans compte
        $memberLyon1 = new ClubMember()
            ->setFirstName('Thomas')
            ->setLastName('Petit')
            ->setClub($clubB);
        $manager->persist($memberLyon1);

        $memberLyon2 = new ClubMember()
            ->setFirstName('Nathalie')
            ->setLastName('Simon')
            ->setEmail('nathalie.simon@kyudo.fr')
            ->setClub($clubB);
        $manager->persist($memberLyon2);

        // ────────────────────────────────────────────────────────────────────
        // Équipements — Gants (Yugake)
        // ────────────────────────────────────────────────────────────────────

        // CLUB — Club A (Paris Marais)
        $yugakeA1 = new Yugake()
            ->setOwnerClub($clubA)
            ->setNbFingers(3)
            ->setSize('8');
        $manager->persist($yugakeA1);

        $yugakeA2 = new Yugake()
            ->setOwnerClub($clubA)
            ->setNbFingers(3)
            ->setSize('7');
        $manager->persist($yugakeA2);

        // CLUB — Club B (Lyon) — avec emprunteur club (Vincennes emprunte à Lyon)
        $yugakeB = new Yugake()
            ->setOwnerClub($clubB)
            ->setNbFingers(3)
            ->setSize('9')
            ->setBorrowerClub($clubC);
        $manager->persist($yugakeB);

        // CLUB — Club C (Vincennes)
        $yugakeC = new Yugake()
            ->setOwnerClub($clubC)
            ->setNbFingers(3)
            ->setSize('6');
        $manager->persist($yugakeC);

        // CLUB — Club G (Rennes)
        $yugakeG = new Yugake()
            ->setOwnerClub($clubG)
            ->setNbFingers(4)
            ->setSize('L');
        $manager->persist($yugakeG);

        // REGIONAL — Région A (Ile de France)
        $yugakeRegA = new Yugake()
            ->setOwnerRegion($regionA)
            ->setEquipmentLevel(EquipmentLevel::REGIONAL)
            ->setNbFingers(3)
            ->setSize('8');
        $manager->persist($yugakeRegA);

        // REGIONAL — Région C (Arc Atlantique)
        $yugakeRegC = new Yugake()
            ->setOwnerRegion($regionC)
            ->setEquipmentLevel(EquipmentLevel::REGIONAL)
            ->setNbFingers(3)
            ->setSize('7');
        $manager->persist($yugakeRegC);

        // NATIONAL
        $yugakeNat = new Yugake()
            ->setOwnerFederation($federation)
            ->setEquipmentLevel(EquipmentLevel::NATIONAL)
            ->setNbFingers(5)
            ->setSize('10');
        $manager->persist($yugakeNat);

        // NATIONAL — emprunté (pour tester la restriction "dispo seulement" des rôles < CN)
        $yugakeNatBorrowed = new Yugake()
            ->setOwnerFederation($federation)
            ->setEquipmentLevel(EquipmentLevel::NATIONAL)
            ->setNbFingers(3)
            ->setSize('8')
            ->setBorrowerMember($memberLinked);
        $manager->persist($yugakeNatBorrowed);

        // REGIONAL — Région A (Ile de France) — emprunté
        // (pour tester la restriction "dispo seulement" de MEMBER / PRESIDENT / MANAGER_CLUB)
        $yugakeRegABorrowed = new Yugake()
            ->setOwnerRegion($regionA)
            ->setEquipmentLevel(EquipmentLevel::REGIONAL)
            ->setNbFingers(4)
            ->setSize('7')
            ->setBorrowerClub($clubA);
        $manager->persist($yugakeRegABorrowed);

        // ────────────────────────────────────────────────────────────────────
        // Équipements — Arcs (Yumi)
        // ────────────────────────────────────────────────────────────────────

        // CLUB — Club A (Paris Marais)
        $yumiA1 = new Yumi()
            ->setOwnerClub($clubA)
            ->setMaterial('bambou')
            ->setStrength(14)
            ->setYumiLength(YumiLength::NAMISUN);
        $manager->persist($yumiA1);

        $yumiA2 = new Yumi()
            ->setOwnerClub($clubA)
            ->setMaterial('carbone')
            ->setStrength(12)
            ->setYumiLength(YumiLength::NISUN_NOBI);
        $manager->persist($yumiA2);

        // CLUB — Club D (Ryushin Dojo Paris) — avec emprunteur membre
        $yumiD = new Yumi()
            ->setOwnerClub($clubD)
            ->setMaterial('bambou')
            ->setStrength(16)
            ->setYumiLength(YumiLength::YONSUN_NOBI)
            ->setBorrowerMember($memberLinked);
        $manager->persist($yumiD);

        // CLUB — Club E (Grenoble)
        $yumiE = new Yumi()
            ->setOwnerClub($clubE)
            ->setMaterial('fibre de verre')
            ->setStrength(10)
            ->setYumiLength(YumiLength::NAMISUN);
        $manager->persist($yumiE);

        // CLUB — Club G (Rennes)
        $yumiG = new Yumi()
            ->setOwnerClub($clubG)
            ->setMaterial('carbone')
            ->setStrength(13)
            ->setYumiLength(YumiLength::NISUN_NOBI);
        $manager->persist($yumiG);

        // REGIONAL — Région B (Auvergne Rhone Alpe)
        $yumiRegB1 = new Yumi()
            ->setOwnerRegion($regionB)
            ->setEquipmentLevel(EquipmentLevel::REGIONAL)
            ->setMaterial('bambou')
            ->setStrength(15)
            ->setYumiLength(YumiLength::NAMISUN);
        $manager->persist($yumiRegB1);

        $yumiRegB2 = new Yumi()
            ->setOwnerRegion($regionB)
            ->setEquipmentLevel(EquipmentLevel::REGIONAL)
            ->setMaterial('carbone')
            ->setStrength(11)
            ->setYumiLength(YumiLength::YONSUN_NOBI);
        $manager->persist($yumiRegB2);

        // NATIONAL — 2 arcs fédéraux de démonstration
        $yumiNat1 = new Yumi()
            ->setOwnerFederation($federation)
            ->setEquipmentLevel(EquipmentLevel::NATIONAL)
            ->setMaterial('bambou')
            ->setStrength(18)
            ->setYumiLength(YumiLength::NAMISUN);
        $manager->persist($yumiNat1);

        $yumiNat2 = new Yumi()
            ->setOwnerFederation($federation)
            ->setEquipmentLevel(EquipmentLevel::NATIONAL)
            ->setMaterial('carbone')
            ->setStrength(14)
            ->setYumiLength(YumiLength::NISUN_NOBI);
        $manager->persist($yumiNat2);

        $manager->flush();
    }
}
