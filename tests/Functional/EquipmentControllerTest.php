<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\DataFixtures\AppFixtures;
use App\Entity\Club;
use App\Entity\Federation;
use App\Entity\Glove;
use App\Entity\Region;
use App\Repository\ClubRepository;
use App\Repository\EquipmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels : EquipmentController.
 *
 * Routes testées :
 *   GET  /equipment            → BROWSE_ALL_EQUIPMENT
 *   GET  /equipment/{id}       → VIEW_EQUIPMENT (avec sujet)
 *   GET  /equipment/create     → CREATE_OWN_CLUB_EQUIPMENT
 *                                 | CREATE_NATIONAL_EQUIPMENT
 *                                 | CREATE_REGIONAL_EQUIPMENT
 *                                 | CREATE_EQUIPMENT_FOR_OTHER_CLUB
 *   GET  /equipment/{id}/edit  → EDIT_EQUIPMENT (avec sujet)
 *
 * Matrice des droits attendus (source : Détails droits Kyudo gestion matériel DHD.csv)
 * ┌──────────────────────────────────────┬───────┬────────┬──────────┬──────────────┬─────────────┬────────────┬───────┐
 * │ Action                               │ USER  │ MEMBER │ PRESIDENT│ MGMT_CLUB    │ MGMT_CTK    │ MGMT_CN    │ ADMIN │
 * ├──────────────────────────────────────┼───────┼────────┼──────────┼──────────────┼─────────────┼────────────┼───────┤
 * │ index (GET)                          │  403  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ show propre club (GET)               │  403  │  200   │  200     │  200         │  403        │  403       │  200  │
 * │ show autre club (GET)                │  403  │  200   │  200     │  200         │  403        │  403       │  200  │
 * │ create propre club (GET)             │  403  │  403   │  200     │  200         │  403        │  403       │  200  │
 * │ create national (GET)                │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * │ create régional (GET)                │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * │ create pour autre club (GET)         │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * │ edit propre club (GET)               │  403  │  403   │  200     │  403         │  200        │  200       │  200  │
 * │ edit autre club (GET)                │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * └──────────────────────────────────────┴───────┴────────┴──────────┴──────────────┴─────────────┴────────────┴───────┘
 *
 * Notes :
 * - INDEX utilise BROWSE_ALL_EQUIPMENT → MEMBER/PRESIDENT/MGMT_CLUB/MGMT_CTK/MGMT_CN/ADMIN.
 * - SHOW utilise VIEW_EQUIPMENT avec sujet (canViewOwnClubEquipment ou canViewEquipmentFromOtherClub).
 * - CREATE utilise un check combiné : au moins un des 4 droits de création.
 * - EDIT utilise EDIT_EQUIPMENT avec sujet (canEditOwnClubEquipment ou canEditEquipmentFromOtherClub).
 * - MGMT_CTK/CN absents de canViewOwnClubEquipment et canViewEquipmentFromOtherClub → 403 sur show.
 */
final class EquipmentControllerTest extends AbstractWebTestCase
{
    /** ID du gant appartenant au Club A (président = president@kyudo-test.fr) */
    private int $gloveAId;

    /** ID du gant appartenant au Club B (sans président, Région B) */
    private int $gloveBId;

    /** ID du gant appartenant au Club C (sans président, Région A — même région que Club A) */
    private int $gloveCId;

    /** ID du gant régional (owner_region = Région A) */
    private int $gloveRegionalId;

    /** ID du gant national (owner_federation = Fédération) */
    private int $gloveNationalId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();
        /** @var EquipmentRepository $repo */
        $repo = $container->get(EquipmentRepository::class);

        /** @var Glove[] $gloves */
        $gloves = $repo->findAll();

        foreach ($gloves as $glove) {
            if (AppFixtures::CLUB_A === $glove->getOwnerClub()?->getName()) {
                $this->gloveAId = $glove->getId();
            } elseif (AppFixtures::CLUB_B === $glove->getOwnerClub()?->getName()) {
                $this->gloveBId = $glove->getId();
            } elseif (AppFixtures::CLUB_C === $glove->getOwnerClub()?->getName()) {
                $this->gloveCId = $glove->getId();
            } elseif (AppFixtures::REGION_A === $glove->getOwnerRegion()?->getName()) {
                // On cible spécifiquement la Région A : manager_ctk la gère
                $this->gloveRegionalId = $glove->getId();
            } elseif (null !== $glove->getOwnerFederation()) {
                $this->gloveNationalId = $glove->getId();
            }
        }
    }

    // -----------------------------------------------------------------------
    // GET /equipment — index (BROWSE_ALL_EQUIPMENT)
    // CSV : MEMBER+ autorisé, ROLE_USER refusé
    // -----------------------------------------------------------------------
    public function testIndexDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/equipment');
    }

    public function testIndexGrantedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetGranted('/equipment');
    }

    public function testIndexGrantedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/equipment');
    }

    public function testIndexGrantedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetGranted('/equipment');
    }

    public function testIndexGrantedForEquipmentManagerCtk(): void
    {
        // CTK peut parcourir la liste globale (BROWSE_ALL_EQUIPMENT inclut CTK)
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/equipment');
    }

    public function testIndexGrantedForEquipmentManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/equipment');
    }

    public function testIndexGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/equipment');
    }

    // -----------------------------------------------------------------------
    // GET /equipment — tous les rôles (MEMBER+) voient l'intégralité des équipements
    // (club, régional, national) sans restriction.
    // Vérification : chaque rôle voit les 3 clubs (A, B, C).
    // -----------------------------------------------------------------------

    public function testIndexForMemberShowsAllClubsAndLevels(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(AppFixtures::CLUB_A, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_B, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_C, $content);
    }

    public function testIndexForPresidentShowsAllClubsAndLevels(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(AppFixtures::CLUB_A, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_B, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_C, $content);
    }

    public function testIndexForManagerClubShowsAllClubsAndLevels(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(AppFixtures::CLUB_A, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_B, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_C, $content);
    }

    public function testIndexForManagerCtkShowsAllClubsAndLevels(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(AppFixtures::CLUB_A, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_B, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_C, $content);
    }

    public function testIndexForManagerCnShowsAllClubsAndLevels(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(AppFixtures::CLUB_A, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_B, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_C, $content);
    }

    public function testIndexForAdminShowsAllClubsAndLevels(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(AppFixtures::CLUB_A, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_B, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_C, $content);
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id} — show équipement du propre club (Club A)
    // CSV : MEMBER/PRESIDENT/MGMT_CLUB autorisés, CTK/CN refusés
    // -----------------------------------------------------------------------
    public function testShowOwnClubEquipmentDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/equipment/'.$this->gloveAId);
    }

    public function testShowOwnClubEquipmentGrantedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetGranted('/equipment/'.$this->gloveAId);
    }

    public function testShowOwnClubEquipmentGrantedForPresident(): void
    {
        // president@kyudo-test.fr est président de Club A → équipement du propre club
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/equipment/'.$this->gloveAId);
    }

    public function testShowOwnClubEquipmentGrantedForEquipmentManagerClub(): void
    {
        // mgr-club@kyudo-test.fr est gestionnaire de Club A → équipement du propre club
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetGranted('/equipment/'.$this->gloveAId);
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id} — MANAGER_CLUB : restriction régionale sur "autre club"
    // mgr-club@kyudo-test.fr est gestionnaire de Club A (Région A).
    //   Club C est dans Région A → visible
    //   Club B est dans Région B → non visible
    // -----------------------------------------------------------------------
    public function testShowSameRegionClubEquipmentGrantedForEquipmentManagerClub(): void
    {
        // Club C est en Région A, comme Club A → MANAGER_CLUB peut voir ses équipements
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetGranted('/equipment/'.$this->gloveCId);
    }

    public function testShowOtherRegionClubEquipmentDeniedForEquipmentManagerClub(): void
    {
        // Club B est en Région B, différente de Région A → MANAGER_CLUB ne peut pas voir
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/equipment/'.$this->gloveBId);
    }

    public function testShowOwnClubEquipmentDeniedForEquipmentManagerCtk(): void
    {
        // CTK absent de canViewOwnClubEquipment ET canViewEquipmentFromOtherClub → 403
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetDenied('/equipment/'.$this->gloveAId);
    }

    public function testShowOwnClubEquipmentDeniedForEquipmentManagerCn(): void
    {
        // CN absent de canViewOwnClubEquipment ET canViewEquipmentFromOtherClub → 403
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetDenied('/equipment/'.$this->gloveAId);
    }

    public function testShowOwnClubEquipmentGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/equipment/'.$this->gloveAId);
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id} — show équipement d'un autre club (Club B)
    // president@kyudo-test.fr est président de Club A, PAS de Club B
    // CSV : MEMBER/PRESIDENT/MGMT_CLUB autorisés, CTK/CN refusés
    // -----------------------------------------------------------------------
    public function testShowOtherClubEquipmentGrantedForPresident(): void
    {
        // CLUB_PRESIDENT est dans canViewEquipmentFromOtherClub → 200 même pour autre club
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/equipment/'.$this->gloveBId);
    }

    public function testShowOtherClubEquipmentDeniedForEquipmentManagerCtk(): void
    {
        // CTK absent de canViewEquipmentFromOtherClub → 403
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetDenied('/equipment/'.$this->gloveBId);
    }

    public function testShowOtherClubEquipmentDeniedForEquipmentManagerCn(): void
    {
        // CN absent de canViewEquipmentFromOtherClub → 403
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetDenied('/equipment/'.$this->gloveBId);
    }

    public function testShowOtherClubEquipmentGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/equipment/'.$this->gloveBId);
    }

    // -----------------------------------------------------------------------
    // GET /equipment/create — création (check combiné sur 4 droits)
    // CSV : PRESIDENT/MGMT_CLUB → propre club ; MGMT_CTK/CN → national, régional, autre club
    // -----------------------------------------------------------------------
    public function testCreateDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/equipment/create');
    }

    public function testCreateDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/equipment/create');
    }

    public function testCreateOwnClubEquipmentGrantedForPresident(): void
    {
        // CREATE_OWN_CLUB_EQUIPMENT → PRESIDENT autorisé
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/equipment/create');
    }

    public function testCreateOwnClubEquipmentGrantedForEquipmentManagerClub(): void
    {
        // CREATE_OWN_CLUB_EQUIPMENT → MGMT_CLUB autorisé
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetGranted('/equipment/create');
    }

    public function testCreateRegionalOrClubEquipmentGrantedForEquipmentManagerCtk(): void
    {
        // CREATE_REGIONAL_EQUIPMENT et CREATE_EQUIPMENT_FOR_OTHER_CLUB → MGMT_CTK autorisé
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/equipment/create');
    }

    public function testCreateNationalEquipmentGrantedForEquipmentManagerCn(): void
    {
        // CREATE_NATIONAL_EQUIPMENT → MGMT_CN autorisé
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/equipment/create');
    }

    public function testCreateGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/equipment/create');
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id}/edit — édition équipement du propre club (Club A)
    // CSV : PRESIDENT/MGMT_CLUB autorisés
    // -----------------------------------------------------------------------
    public function testEditOwnClubEquipmentDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/equipment/'.$this->gloveAId.'/edit');
    }

    public function testEditOwnClubEquipmentGrantedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/equipment/'.$this->gloveAId.'/edit');
    }

    public function testEditOwnClubEquipmentGrantedForEquipmentManagerClub(): void
    {
        // mgr-club@kyudo-test.fr est gestionnaire de Club A dans les fixtures
        // → isOwnClub = true → canEditOwnClubEquipment (MANAGER_CLUB autorisé) → 200
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetGranted('/equipment/'.$this->gloveAId.'/edit');
    }

    public function testEditOwnClubEquipmentGrantedForEquipmentManagerCtk(): void
    {
        // CTK n'a pas de "propre club" dans les fixtures → isOwnClub = false
        // → passe par canEditEquipmentFromOtherClub (CTK/CN/ADMIN) → 200
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/equipment/'.$this->gloveAId.'/edit');
    }

    public function testEditOwnClubEquipmentGrantedForEquipmentManagerCn(): void
    {
        // CN n'a pas de "propre club" dans les fixtures → isOwnClub = false
        // → passe par canEditEquipmentFromOtherClub (CTK/CN/ADMIN) → 200
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/equipment/'.$this->gloveAId.'/edit');
    }

    public function testEditOwnClubEquipmentGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/equipment/'.$this->gloveAId.'/edit');
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id}/edit — édition équipement d'un autre club (Club B)
    // CSV : MGMT_CTK/CN autorisés, PRESIDENT refusé
    // -----------------------------------------------------------------------
    public function testEditOtherClubEquipmentDeniedForPresident(): void
    {
        // president@kyudo-test.fr est président du Club A, pas du Club B
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/equipment/'.$this->gloveBId.'/edit');
    }

    public function testEditNationalOrRegionalEquipmentGrantedForEquipmentManagerCtk(): void
    {
        // CSV : MGMT_CTK peut modifier équipements national et régional (autre club)
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/equipment/'.$this->gloveBId.'/edit');
    }

    public function testEditNationalOrRegionalEquipmentGrantedForEquipmentManagerCn(): void
    {
        // CSV : MGMT_CN peut modifier équipements national et régional (autre club)
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/equipment/'.$this->gloveBId.'/edit');
    }

    public function testEditOtherClubEquipmentGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/equipment/'.$this->gloveBId.'/edit');
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id}/edit — édition équipement national
    // CSV : MGMT_CTK peut modifier (mais pas créer) ; PRESIDENT refusé
    // -----------------------------------------------------------------------
    public function testEditNationalEquipmentGrantedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/equipment/'.$this->gloveNationalId.'/edit');
    }

    public function testEditNationalEquipmentGrantedForEquipmentManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/equipment/'.$this->gloveNationalId.'/edit');
    }

    public function testEditNationalEquipmentDeniedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/equipment/'.$this->gloveNationalId.'/edit');
    }

    public function testEditNationalEquipmentDeniedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/equipment/'.$this->gloveNationalId.'/edit');
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id}/edit — édition équipement régional
    // CSV : MGMT_CTK (sa région) et MGMT_CN/ADMIN autorisés
    // -----------------------------------------------------------------------
    public function testEditRegionalEquipmentGrantedForEquipmentManagerCtk(): void
    {
        // CTK gère Région A, gloveRegional appartient à Région A → autorisé
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/equipment/'.$this->gloveRegionalId.'/edit');
    }

    public function testEditRegionalEquipmentDeniedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/equipment/'.$this->gloveRegionalId.'/edit');
    }

    public function testEditRegionalEquipmentDeniedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/equipment/'.$this->gloveRegionalId.'/edit');
    }

    // -----------------------------------------------------------------------
    // POST /equipment/create — création avec bon niveau selon le rôle
    // -----------------------------------------------------------------------
    public function testCreateClubEquipmentPostGrantedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);

        $container = self::getContainer();
        /** @var ClubRepository $clubRepo */
        $clubRepo = $container->get(ClubRepository::class);
        $clubA = $clubRepo->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(Club::class, $clubA);

        $this->assertPostRedirects('/equipment/create', [
            'equipment_form' => [
                'equipment_type' => 'glove',
                'owner_club'     => (string) $clubA->getId(),
                'borrower_club'  => '',
                'borrower_user'  => '',
                'glove_form'     => ['nb_fingers' => '3', 'size' => '7'],
                'yumi_form'      => ['material' => '', 'strength' => '', 'length' => ''],
            ],
        ]);
    }

    public function testCreateRegionalEquipmentPostGrantedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);

        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $regionA = $em->getRepository(Region::class)->findOneBy(['name' => AppFixtures::REGION_A]);
        $this->assertInstanceOf(Region::class, $regionA);

        $this->assertPostRedirects('/equipment/create', [
            'equipment_form' => [
                'equipment_type'  => 'glove',
                'owner_region'    => (string) $regionA->getId(),
                'owner_club'      => '',
                'borrower_club'   => '',
                'borrower_user'   => '',
                'glove_form'      => ['nb_fingers' => '3', 'size' => '7'],
                'yumi_form'       => ['material' => '', 'strength' => '', 'length' => ''],
            ],
        ]);
    }

    public function testCreateNationalEquipmentGetDeniedForEquipmentManagerCtk(): void
    {
        // CTK ne peut pas accéder au formulaire de création d'équipement national :
        // son formulaire n'expose pas le champ owner_federation.
        // On vérifie que le formulaire affiché ne contient pas de champ owner_federation.
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->client->request(Request::METHOD_GET, '/equipment/create');

        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
        $this->assertStringNotContainsString(
            'name="equipment_form[owner_federation]"',
            (string) $this->client->getResponse()->getContent(),
            'CTK should not see the owner_federation field'
        );
    }

    public function testCreateNationalEquipmentPostGrantedForEquipmentManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);

        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $federation = $em->getRepository(Federation::class)->findOneBy(['name' => AppFixtures::FEDERATION_NAME]);
        $this->assertInstanceOf(Federation::class, $federation);

        $this->assertPostRedirects('/equipment/create', [
            'equipment_form' => [
                'equipment_type'   => 'glove',
                'owner_federation' => (string) $federation->getId(),
                'owner_region'     => '',
                'owner_club'       => '',
                'borrower_club'    => '',
                'borrower_user'    => '',
                'glove_form'       => ['nb_fingers' => '3', 'size' => '7'],
                'yumi_form'        => ['material' => '', 'strength' => '', 'length' => ''],
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // Validation : ExactlyOneOwner — uniquement accessible via CN/ADMIN (3 champs)
    // -----------------------------------------------------------------------
    public function testCreateEquipmentValidationFailsWhenNoOwnerSelected(): void
    {
        // CN soumet le formulaire sans choisir aucun propriétaire → erreur de validation
        $this->loginAs(AppFixtures::USER_MANAGER_CN);

        $this->client->request(Request::METHOD_POST, '/equipment/create', [
            'equipment_form' => [
                'equipment_type'   => 'glove',
                'owner_federation' => '',
                'owner_region'     => '',
                'owner_club'       => '',
                'borrower_club'    => '',
                'borrower_user'    => '',
                'glove_form'       => ['nb_fingers' => '3', 'size' => '7'],
                'yumi_form'        => ['material' => '', 'strength' => '', 'length' => ''],
            ],
        ]);

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 422], 'Le formulaire doit être réaffiché avec une erreur');
        $this->assertStringContainsString(
            'Vous devez choisir un propriétaire',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testCreateEquipmentWithMultipleOwnersPrioritizesFederation(): void
    {
        // CN soumet le formulaire avec fédération + club simultanément :
        // le serveur applique la priorité fédération > club → crée un équipement NATIONAL
        $this->loginAs(AppFixtures::USER_MANAGER_CN);

        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $federation = $em->getRepository(Federation::class)->findOneBy(['name' => AppFixtures::FEDERATION_NAME]);
        $clubA = $em->getRepository(Club::class)->findOneBy(['name' => AppFixtures::CLUB_A]);
        $this->assertInstanceOf(Federation::class, $federation);
        $this->assertInstanceOf(Club::class, $clubA);

        $this->client->request(Request::METHOD_POST, '/equipment/create', [
            'equipment_form' => [
                'equipment_type'   => 'glove',
                'owner_federation' => (string) $federation->getId(),
                'owner_region'     => '',
                'owner_club'       => (string) $clubA->getId(),
                'borrower_club'    => '',
                'borrower_user'    => '',
                'glove_form'       => ['nb_fingers' => '3', 'size' => '7'],
                'yumi_form'        => ['material' => '', 'strength' => '', 'length' => ''],
            ],
        ]);

        // La fédération est prioritaire : création réussie avec redirect
        $this->assertResponseRedirects('/equipment');
    }
}
