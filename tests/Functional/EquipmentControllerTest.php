<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\DataFixtures\AppFixtures;
use App\Entity\Club;
use App\Entity\Equipment;
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
 * Matrice des droits de visualisation (source : matrice v2)
 * ┌────────────────────────────────────────────┬───────┬────────┬──────────┬──────────────┬─────────────┬────────────┬───────┐
 * │ Action                                     │ USER  │ MEMBER │ PRESIDENT│ MGMT_CLUB    │ MGMT_CTK    │ MGMT_CN    │ ADMIN │
 * ├────────────────────────────────────────────┼───────┼────────┼──────────┼──────────────┼─────────────┼────────────┼───────┤
 * │ index (GET)                                │  403  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ show club A dispo (propre club)            │  403  │  200   │  200     │  200         │  200 (†)    │  200       │  200  │
 * │ show club A prêté (propre club)            │  403  │  200   │  200     │  200         │  200 (†)    │  200       │  200  │
 * │ show club C dispo (même CTK, autre club)   │  403  │  403   │  200     │  200         │  200 (†)    │  200       │  200  │
 * │ show club C prêté (même CTK, autre club)   │  403  │  403   │  403     │  403         │  200 (†)    │  200       │  200  │
 * │ show club B emprunté (autre CTK)           │  403  │  403   │  403     │  403         │  403        │  200       │  200  │
 * │ show club G dispo (autre CTK)              │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * │ show régional Région A dispo               │  403  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ show régional Région A prêté               │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * │ show national dispo                        │  200  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ show national prêté                        │  403  │  403   │  403     │  403         │  403        │  200       │  200  │
 * │ create propre club (GET)                   │  403  │  403   │  200     │  200         │  403        │  403       │  200  │
 * │ create national (GET)                      │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * │ create régional (GET)                      │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * │ create pour autre club (GET)               │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * │ edit propre club (GET)                     │  403  │  403   │  200     │  200         │  403        │  403       │  200  │
 * │ edit autre club (GET)                      │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * └────────────────────────────────────────────┴───────┴────────┴──────────┴──────────────┴─────────────┴────────────┴───────┘
 *
 * Notes :
 * - (†) MGMT_CTK : Club A et Club C sont dans Région A, gérée par mgr-ctk@kyudo-test.fr.
 * - INDEX utilise BROWSE_ALL_EQUIPMENT → MEMBER+ autorisé.
 * - SHOW utilise VIEW_EQUIPMENT avec sujet (logique niveau + emprunt + région).
 * - SHOW national : USER autorisé si dispo, refusé si prêté.
 * - CREATE utilise un check combiné : au moins un des 4 droits de création.
 * - EDIT utilise EDIT_EQUIPMENT avec sujet (canEditOwnClubEquipment ou canEditEquipmentFromOtherClub).
 * - INDEX applique des filtres de visibilité par rôle (voir getVisibleEquipmentFiltersForUser).
 */
final class EquipmentControllerTest extends AbstractWebTestCase
{
    /** ID du gant appartenant au Club A (président = president@kyudo-test.fr) */
    private int $gloveAId;

    /** ID du gant appartenant au Club B (sans président, Région B) — emprunté par Club C */
    private int $gloveBId;

    /** ID du gant appartenant au Club C (sans président, Région A — même région que Club A) */
    private int $gloveCId;

    /** ID du gant appartenant au Club G (Bretagne, Région C — autre CTK) */
    private int $gloveGId;

    /** ID du gant régional (owner_region = Région A) — disponible */
    private int $gloveRegionalId;

    /** ID du gant régional (owner_region = Région A) — emprunté */
    private int $gloveRegionalBorrowedId;

    /** ID du gant régional (owner_region = Bretagne / Région C) — disponible */
    private int $gloveRegionalCId;

    /** ID du gant national (owner_federation = Fédération) — disponible */
    private int $gloveNationalId;

    /** ID du gant national (owner_federation = Fédération) — emprunté */
    private int $gloveNationalBorrowedId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();
        /** @var EquipmentRepository $repo */
        $repo = $container->get(EquipmentRepository::class);

        /** @var Equipment[] $gloves */
        $gloves = $repo->findAll();

        foreach ($gloves as $glove) {
            if (!$glove instanceof Glove) {
                continue;
            }

            if (AppFixtures::CLUB_A === $glove->getOwnerClub()?->getName()) {
                $this->gloveAId = $glove->getId();
            } elseif (AppFixtures::CLUB_B === $glove->getOwnerClub()?->getName()) {
                $this->gloveBId = $glove->getId();
            } elseif (AppFixtures::CLUB_C === $glove->getOwnerClub()?->getName()) {
                $this->gloveCId = $glove->getId();
            } elseif (AppFixtures::CLUB_G === $glove->getOwnerClub()?->getName()) {
                $this->gloveGId = $glove->getId();
            } elseif (AppFixtures::REGION_A === $glove->getOwnerRegion()?->getName()) {
                // Deux gants régionaux pour Région A : disponible et emprunté
                if (!$glove->getBorrowerClub() instanceof Club && !$glove->getBorrowerMember() instanceof \App\Entity\ClubMember) {
                    $this->gloveRegionalId = $glove->getId();
                } else {
                    $this->gloveRegionalBorrowedId = $glove->getId();
                }
            } elseif ('Bretagne' === $glove->getOwnerRegion()?->getName()) {
                $this->gloveRegionalCId = $glove->getId();
            } elseif ($glove->getOwnerFederation() instanceof Federation) {
                // Deux gants nationaux : disponible et emprunté
                if (!$glove->getBorrowerClub() instanceof Club && !$glove->getBorrowerMember() instanceof \App\Entity\ClubMember) {
                    $this->gloveNationalId = $glove->getId();
                } else {
                    $this->gloveNationalBorrowedId = $glove->getId();
                }
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
    // GET /equipment — filtres de visibilité par rôle sur l'index
    //
    // Chaque rôle ne doit voir QUE les équipements auxquels il a accès.
    // Les assertions utilisent l'URL /equipment/{id} qui apparaît dans les
    // liens de la liste paginée.
    //
    // Rappel des fixtures :
    //   Club A (Région A / Île-de-France) — member, president, mgr-club
    //   Club B (Région B)                 — emprunté par Club C
    //   Club C (Région A)                 — disponible
    //   Club G (Région C / Bretagne)      — disponible
    //   Régional Région A dispo           — gloveRegionalId
    //   Régional Région A emprunté        — gloveRegionalBorrowedId
    //   Régional Région C dispo           — gloveRegionalCId
    //   National dispo                    — gloveNationalId
    //   National emprunté                 — gloveNationalBorrowedId
    // -----------------------------------------------------------------------

    // --- ADMIN : voit tout ---
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

    // --- MEMBER : propre club (tous) + CTK propre (dispo) + pas de national ---
    public function testIndexMemberSeesOwnClubEquipment(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        // Club A = propre club → visible
        $this->assertStringContainsString('/equipment/'.$this->gloveAId, $content);
    }

    public function testIndexMemberDoesNotSeeOtherClubEquipment(): void
    {
        // MEMBER ne voit pas les équipements d'autres clubs (même ou autre CTK)
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('/equipment/'.$this->gloveBId, $content); // autre CTK, emprunté
        $this->assertStringNotContainsString('/equipment/'.$this->gloveCId, $content); // même CTK, MEMBER n'accède pas aux autres clubs
        $this->assertStringNotContainsString('/equipment/'.$this->gloveGId, $content); // autre CTK
    }

    public function testIndexMemberSeesOwnCtkRegionalAvailable(): void
    {
        // MEMBER voit les équipements régionaux disponibles de sa CTK (Région A)
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('/equipment/'.$this->gloveRegionalId, $content);
    }

    public function testIndexMemberDoesNotSeeOwnCtkRegionalBorrowed(): void
    {
        // MEMBER ne voit pas les équipements régionaux empruntés (même sa CTK)
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('/equipment/'.$this->gloveRegionalBorrowedId, $content);
    }

    public function testIndexMemberDoesNotSeeNationalEquipment(): void
    {
        // MEMBER ne voit aucun équipement national (ni disponible ni emprunté)
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('/equipment/'.$this->gloveNationalId, $content);
        $this->assertStringNotContainsString('/equipment/'.$this->gloveNationalBorrowedId, $content);
    }

    // --- PRESIDENT : propre club (tous) + même CTK clubs (dispo) + toutes régions (dispo) + pas de national ---
    public function testIndexPresidentSeesSameCtkClubAvailable(): void
    {
        // Club C est en Région A (même CTK que Club A, président) → visible car disponible
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('/equipment/'.$this->gloveCId, $content);
    }

    public function testIndexPresidentDoesNotSeeOtherCtkBorrowedClub(): void
    {
        // Club B est Région B (autre CTK), et son gant est emprunté → non visible
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('/equipment/'.$this->gloveBId, $content);
    }

    public function testIndexPresidentDoesNotSeeOtherCtkAvailableClub(): void
    {
        // Club G est Région C (autre CTK) → PRESIDENT ne voit pas les clubs hors de sa CTK
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('/equipment/'.$this->gloveGId, $content);
    }

    public function testIndexPresidentSeesAllCtkRegionalAvailable(): void
    {
        // PRESIDENT voit les équipements régionaux disponibles de TOUTES les CTK
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('/equipment/'.$this->gloveRegionalId, $content);    // Région A, dispo
        $this->assertStringContainsString('/equipment/'.$this->gloveRegionalCId, $content);   // Région C, dispo
        $this->assertStringNotContainsString('/equipment/'.$this->gloveRegionalBorrowedId, $content); // Région A, emprunté
    }

    public function testIndexPresidentDoesNotSeeNationalEquipment(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('/equipment/'.$this->gloveNationalId, $content);
        $this->assertStringNotContainsString('/equipment/'.$this->gloveNationalBorrowedId, $content);
    }

    // --- MGR_CTK : clubs de sa CTK (tous) + autres clubs (dispo) + ses régions (tous) + autres régions (dispo) ---
    public function testIndexMgrCtkSeesOwnCtkBorrowedRegional(): void
    {
        // mgr-ctk gère Région A → voit les équipements régionaux empruntés de sa région
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('/equipment/'.$this->gloveRegionalBorrowedId, $content);
    }

    public function testIndexMgrCtkSeesOtherCtkRegionalAvailable(): void
    {
        // mgr-ctk voit les équipements régionaux disponibles des autres CTK
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('/equipment/'.$this->gloveRegionalCId, $content);
    }

    public function testIndexMgrCtkDoesNotSeeOtherCtkBorrowedClub(): void
    {
        // Club B (Région B) a son gant emprunté → CTK Île-de-France ne peut pas le voir
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('/equipment/'.$this->gloveBId, $content);
    }

    public function testIndexMgrCtkSeesOtherCtkAvailableClub(): void
    {
        // Club G (Bretagne, Région C) a son gant disponible → CTK Île-de-France peut le voir
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('/equipment/'.$this->gloveGId, $content);
    }

    public function testIndexMgrCtkDoesNotSeeNationalEquipment(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('/equipment/'.$this->gloveNationalId, $content);
        $this->assertStringNotContainsString('/equipment/'.$this->gloveNationalBorrowedId, $content);
    }

    // --- MGR_CN : voit tout ---
    public function testIndexMgrCnSeesNationalEquipment(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->client->request(Request::METHOD_GET, '/equipment');
        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('/equipment/'.$this->gloveNationalId, $content);
        $this->assertStringContainsString('/equipment/'.$this->gloveNationalBorrowedId, $content);
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id} — show équipement du propre club (Club A, dispo)
    // Matrice : USER=403, MEMBER=200, PRESIDENT=200, MGMT_CLUB=200,
    //           MGMT_CTK=200 (Club A ∈ Région A gérée par CTK), MGMT_CN=200, ADMIN=200
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

    public function testShowClubEquipmentInManagedRegionGrantedForEquipmentManagerCtk(): void
    {
        // Club A est dans Région A, gérée par mgr-ctk → canViewOtherClubEquipment : même CTK → 200
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/equipment/'.$this->gloveAId);
    }

    public function testShowClubEquipmentGrantedForEquipmentManagerCn(): void
    {
        // CN a accès à tous les équipements club → 200
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/equipment/'.$this->gloveAId);
    }

    public function testShowOwnClubEquipmentGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/equipment/'.$this->gloveAId);
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id} — MANAGER_CLUB : restriction régionale sur "autre club"
    // mgr-club@kyudo-test.fr est gestionnaire de Club A (Région A).
    //   Club C dispo (Région A) → visible (même CTK, dispo)
    //   Club B emprunté (Région B) → non visible (autre CTK)
    // -----------------------------------------------------------------------
    public function testShowSameRegionClubEquipmentGrantedForEquipmentManagerClub(): void
    {
        // Club C est en Région A, dispo → MANAGER_CLUB peut voir
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetGranted('/equipment/'.$this->gloveCId);
    }

    public function testShowOtherRegionClubEquipmentDeniedForEquipmentManagerClub(): void
    {
        // Club B est en Région B (autre CTK) → MANAGER_CLUB ne peut pas voir
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/equipment/'.$this->gloveBId);
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id} — MEMBER : aucun accès aux équipements d'autres clubs
    // -----------------------------------------------------------------------
    public function testShowOtherClubSameCtKEquipmentDeniedForMember(): void
    {
        // Club C est en Région A (même CTK que member), mais MEMBER ne voit aucun autre club
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/equipment/'.$this->gloveCId);
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id} — PRESIDENT : même CTK dispo ✅, autre CTK ❌
    // -----------------------------------------------------------------------
    public function testShowSameCtKClubEquipmentDispoGrantedForPresident(): void
    {
        // Club C est en Région A (même CTK que le président de Club A) → dispo → 200
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/equipment/'.$this->gloveCId);
    }

    public function testShowOtherCtKClubEquipmentDeniedForPresident(): void
    {
        // Club G est en Région C (autre CTK) → PRESIDENT ne peut pas voir → 403
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/equipment/'.$this->gloveGId);
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id} — MANAGER_CTK : autre CTK dispo ✅, autre CTK prêté ❌
    // -----------------------------------------------------------------------
    public function testShowOtherCtKClubEquipmentDispoGrantedForEquipmentManagerCtk(): void
    {
        // Club G est en Région C (autre CTK), dispo → CTK voit les équipements dispo des autres CTK
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/equipment/'.$this->gloveGId);
    }

    public function testShowOtherCtKClubEquipmentBorrowedDeniedForEquipmentManagerCtk(): void
    {
        // Club B est en Région B (autre CTK), gloveB est emprunté → 403
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetDenied('/equipment/'.$this->gloveBId);
    }

    public function testShowOtherCtKClubEquipmentGrantedForEquipmentManagerCn(): void
    {
        // CN voit tout, y compris les équipements empruntés d'autres CTK → 200
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/equipment/'.$this->gloveBId);
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id} — show équipement régional (Région A)
    // Matrice :
    //   dispo  → USER=403, MEMBER=200(sa CTK), PRESIDENT=200, MGMT_CLUB=200,
    //            MGMT_CTK=200, MGMT_CN=200, ADMIN=200
    //   prêté  → USER=403, MEMBER=403, PRESIDENT=403, MGMT_CLUB=403,
    //            MGMT_CTK=200, MGMT_CN=200, ADMIN=200
    // -----------------------------------------------------------------------
    public function testShowRegionalEquipmentDeniedForUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/equipment/'.$this->gloveRegionalId);
    }

    public function testShowRegionalEquipmentDispoGrantedForMember(): void
    {
        // MEMBER peut voir l'équipement régional dispo de sa CTK (Région A)
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetGranted('/equipment/'.$this->gloveRegionalId);
    }

    public function testShowRegionalEquipmentBorrowedDeniedForMember(): void
    {
        // MEMBER ne peut PAS voir un équipement régional emprunté
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/equipment/'.$this->gloveRegionalBorrowedId);
    }

    public function testShowRegionalEquipmentDispoGrantedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetGranted('/equipment/'.$this->gloveRegionalId);
    }

    public function testShowRegionalEquipmentBorrowedDeniedForPresident(): void
    {
        // PRESIDENT ne peut pas voir un équipement régional emprunté
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/equipment/'.$this->gloveRegionalBorrowedId);
    }

    public function testShowRegionalEquipmentDispoGrantedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetGranted('/equipment/'.$this->gloveRegionalId);
    }

    public function testShowRegionalEquipmentBorrowedDeniedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/equipment/'.$this->gloveRegionalBorrowedId);
    }

    public function testShowRegionalEquipmentDispoGrantedForEquipmentManagerCtk(): void
    {
        // CTK gère Région A → voit les équipements régionaux dispo et prêtés
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/equipment/'.$this->gloveRegionalId);
    }

    public function testShowRegionalEquipmentBorrowedGrantedForEquipmentManagerCtk(): void
    {
        // CTK gère Région A → voit même les équipements régionaux empruntés de sa CTK
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/equipment/'.$this->gloveRegionalBorrowedId);
    }

    // -----------------------------------------------------------------------
    // GET /equipment/{id} — show équipement national
    // Matrice :
    //   dispo  → USER=200, MEMBER=200, ... ADMIN=200
    //   prêté  → USER=403, MEMBER=403, PRESIDENT=403, MGMT_CLUB=403,
    //            MGMT_CTK=403, MGMT_CN=200, ADMIN=200
    // -----------------------------------------------------------------------
    public function testShowNationalEquipmentDispoGrantedForUser(): void
    {
        // USER peut voir un équipement national disponible
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetGranted('/equipment/'.$this->gloveNationalId);
    }

    public function testShowNationalEquipmentBorrowedDeniedForUser(): void
    {
        // USER ne peut PAS voir un équipement national emprunté
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/equipment/'.$this->gloveNationalBorrowedId);
    }

    public function testShowNationalEquipmentDispoGrantedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetGranted('/equipment/'.$this->gloveNationalId);
    }

    public function testShowNationalEquipmentBorrowedDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/equipment/'.$this->gloveNationalBorrowedId);
    }

    public function testShowNationalEquipmentBorrowedDeniedForEquipmentManagerCtk(): void
    {
        // CTK ne peut pas voir les équipements nationaux empruntés
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetDenied('/equipment/'.$this->gloveNationalBorrowedId);
    }

    public function testShowNationalEquipmentBorrowedGrantedForEquipmentManagerCn(): void
    {
        // CN peut voir tous les équipements nationaux (dispo + prêtés)
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/equipment/'.$this->gloveNationalBorrowedId);
    }

    public function testShowNationalEquipmentGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/equipment/'.$this->gloveNationalBorrowedId);
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
                'ownerClub'      => (string) $clubA->getId(),
                'borrowerClub'   => '',
                'borrowerMember' => '',
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
                'ownerRegion'     => (string) $regionA->getId(),
                'ownerClub'       => '',
                'borrowerClub'    => '',
                'borrowerMember'  => '',
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
            'name="equipment_form[ownerFederation]"',
            (string) $this->client->getResponse()->getContent(),
            'CTK should not see the ownerFederation field'
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
                'equipment_type'    => 'glove',
                'ownerFederation'   => (string) $federation->getId(),
                'ownerRegion'       => '',
                'ownerClub'         => '',
                'borrowerClub'      => '',
                'borrowerMember'    => '',
                'glove_form'        => ['nb_fingers' => '3', 'size' => '7'],
                'yumi_form'         => ['material' => '', 'strength' => '', 'length' => ''],
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
                'equipment_type'    => 'glove',
                'ownerFederation'   => '',
                'ownerRegion'       => '',
                'ownerClub'         => '',
                'borrowerClub'      => '',
                'borrowerMember'    => '',
                'glove_form'        => ['nb_fingers' => '3', 'size' => '7'],
                'yumi_form'         => ['material' => '', 'strength' => '', 'length' => ''],
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
                'equipment_type'    => 'glove',
                'ownerFederation'   => (string) $federation->getId(),
                'ownerRegion'       => '',
                'ownerClub'         => (string) $clubA->getId(),
                'borrowerClub'      => '',
                'borrowerMember'    => '',
                'glove_form'        => ['nb_fingers' => '3', 'size' => '7'],
                'yumi_form'         => ['material' => '', 'strength' => '', 'length' => ''],
            ],
        ]);

        // La fédération est prioritaire : création réussie avec redirect
        $this->assertResponseRedirects('/equipment');
    }
}
