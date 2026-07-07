<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests fonctionnels : filtres de recherche sur GET /equipment.
 *
 * Vérifie que les filtres (q, equipmentType, status) retournent
 * les bons équipements. Les filtres réduisent le jeu de façon identique
 * quel que soit le rôle → un seul test par scénario suffit.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * INVENTAIRE DES FIXTURES (19 équipements au total)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Gants (10) :
 *   gakeA1          CLUB      Club A (Paris Marais)    nb_fingers=3  size=8   disponible
 *   gakeA2          CLUB      Club A (Paris Marais)    nb_fingers=3  size=7   disponible
 *   gakeB           CLUB      Club B (Lyon)            nb_fingers=3  size=9   prêté à Club C
 *   gakeC           CLUB      Club C (Vincennes)       nb_fingers=3  size=6   disponible
 *   gakeG           CLUB      Club G (Rennes)          nb_fingers=4  size=7   disponible
 *   gakeRA          REGIONAL  Région A (Ile de France) nb_fingers=3  size=8   disponible
 *   gakeRC          REGIONAL  Région C (Arc Atlantique) nb_fingers=3  size=7   disponible
 *   gakeNat         NATIONAL  Fédération               nb_fingers=5  size=10  disponible
 *   gakeNatBorrowed NATIONAL  Fédération               nb_fingers=3  size=8   prêté à memberLinked
 *   gakeRegABorrowed REGIONAL Région A (Ile de France) nb_fingers=4  size=7   prêté à Club A
 *
 * Arcs (9) :
 *   yumiA1   CLUB      Club A  bambou       14  namisun      disponible
 *   yumiA2   CLUB      Club A  carbone      12  nisun_nobi   disponible
 *   yumiD    CLUB      Club D  bambou       16  yonsun_nobi  prêté à USER_MEMBER (borrowerMember → loaned)
 *   yumiE    CLUB      Club E  fibre verre  10  namisun      disponible
 *   yumiG    CLUB      Club G  carbone      13  nisun_nobi   disponible
 *   yumiRB1  REGIONAL  Rég. B  bambou       15  namisun      disponible
 *   yumiRB2  REGIONAL  Rég. B  carbone      11  yonsun_nobi  disponible
 *   yumiNat1 NATIONAL  Fédé.   bambou       18  namisun      disponible
 *   yumiNat2 NATIONAL  Fédé.   carbone      14  nisun_nobi   disponible
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TOTAUX ATTENDUS
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Sans filtre             → 19
 * equipmentType=yumi      →  9
 * equipmentType=gake     → 10
 *
 * status=all              → 19
 * status=available        → 15  (gakeB + yumiD + gakeNatBorrowed + gakeRegABorrowed → 4 prêtés)
 * status=loaned           →  4  (gakeB + yumiD + gakeNatBorrowed + gakeRegABorrowed)
 *
 * status=available + yumi →  8  (yumiD a un borrowerMember → loaned)
 * status=loaned   + yumi  →  1  (yumiD)
 * status=available + gake →  7  (tous les gakes sauf gakeB, gakeNatBorrowed, gakeRegABorrowed)
 * status=loaned   + gake  →  3  (gakeB + gakeNatBorrowed + gakeRegABorrowed)
 *
 * Recherche textuelle (DefaultSearchStrategy — sans filtre de type) :
 *   Champs indexés : ownerClub.name, borrowerClub.name, ownerRegion.name, CONCAT(id,'')
 *   Les équipements régionaux/nationaux ont ownerClub NULL → trouvables par nom de région propriétaire.
 *
 *   q="paris"   → Club A ("Kyudo Paris Marais") + Club D ("Ryushin Dojo Paris")
 *                  gakeA1, gakeA2, yumiA1, yumiA2, yumiD → 5
 *                  + gakeRegABorrowed (borrowerClub=Club A → "Paris") → 6
 *   q="lyon"    → Club B ("Kyudo Lyon") → gakeB (via owner.name) → 1
 *   q="vincen"  → Club C ("Kyudo Vincennes") via owner.name (gakeC) + via borrower.name (gakeB) → 2
 *   q="ile"     → Région A ("Ile de France") → gakeRA, gakeRegABorrowed → 2
 *   q="arc"     → Région C ("Arc Atlantique") → gakeRC → 1
 *   q="bambou"  → aucun club/région/fédération ne contient "bambou" → 0
 *
 * Recherche textuelle (YumiSearchStrategy — type=yumi) :
 *   Champs indexés : material, strength, yumiLength, ownerClub.name, borrowerClub.name
 *   Équipements régionaux/nationaux trouvables via material/strength/length.
 *
 *   q="bambou"     → yumiA1, yumiD, yumiRB1, yumiNat1 → 4
 *   q="carbone"    → yumiA2, yumiG, yumiRB2, yumiNat2 → 4
 *   q="namisun"    → yumiA1, yumiE, yumiRB1, yumiNat1 → 4
 *   q="nisun_nobi" → yumiA2, yumiG, yumiNat2           → 3
 *   q="14"         → yumiA1 (strength=14), yumiNat2 (strength=14) → 2
 *
 * Recherche textuelle (GakeSearchStrategy — type=gake) :
 *   Champs indexés : nb_fingers, size, ownerClub.name, borrowerClub.name
 *   Équipements régionaux/nationaux trouvables via nb_fingers/size.
 *
 *   q="5"      → gakeNat (nb_fingers=5) → 1
 *   q="3"      → 7 gakes avec nb_fingers=3 (gakeA1,gakeA2,gakeB,gakeC,gakeRA,gakeRC,gakeNatBorrowed)
 *   q="paris"  → Club A → gakeA1, gakeA2 + gakeRegABorrowed (borrowerClub=Club A) → 3
 *   q="vincen" → Club C via owner.name (gakeC) + via borrower.name (gakeB) → 2
 */
final class EquipmentSearchFilterTest extends AbstractWebTestCase
{
    // ─── Totaux globaux ──────────────────────────────────────────────────────
    private const int TOTAL_COUNT = 19;

    private const int TOTAL_YUMI_COUNT = 9;

    private const int TOTAL_GAKE_COUNT = 10;

    // ─── Totaux par status ───────────────────────────────────────────────────

    /** gakeB (borrowerClub) + yumiD (borrowerMember) + gakeNatBorrowed + gakeRegABorrowed → 4 prêtés */
    private const int LOANED_COUNT = 4;

    private const int AVAILABLE_COUNT = 15;

    private const int AVAILABLE_YUMI_COUNT = 8;

    private const int LOANED_YUMI_COUNT = 1;

    private const int AVAILABLE_GAKE_COUNT = 7;

    private const int LOANED_GAKE_COUNT = 3;

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    /**
     * Effectue un GET /equipment avec les paramètres donnés et retourne le Crawler.
     *
     * @param array<string, string> $params
     */
    private function requestIndex(array $params = []): Crawler
    {
        return $this->client->request(Request::METHOD_GET, '/equipment', $params);
    }

    /**
     * Compte les lignes d'équipement réelles dans la réponse HTML.
     * Chaque ligne porte l'attribut data-testid="equipment-row".
     */
    private function countEquipmentRows(Crawler $crawler): int
    {
        return $crawler->filter('[data-testid="equipment-row"]')->count();
    }

    // -----------------------------------------------------------------------
    // A. Aucun filtre
    // -----------------------------------------------------------------------

    public function testNoFilterShowsAllEquipments(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex();
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_COUNT, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // B. Filtre equipmentType=yumi
    // -----------------------------------------------------------------------

    public function testFilterByYumiType(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // C. Filtre equipmentType=gake
    // -----------------------------------------------------------------------

    public function testFilterByGakeType(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'gake']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_GAKE_COUNT, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // D. Filtre status=available
    // Note : le filtre vérifie borrowerClub IS NULL ET borrowerMember IS NULL.
    // gakeB (borrowerClub) + yumiD (borrowerMember) → 2 prêtés, 15 disponibles.
    // -----------------------------------------------------------------------

    public function testFilterAvailable(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_COUNT, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // E. Filtre status=loaned
    // Admin : gakeB + yumiD + gakeNatBorrowed + gakeRegABorrowed → LOANED_COUNT=4.
    // Member (Club A / Ile de France) : ne voit que son club + régional dispo →
    //   Club A n'a aucun équipement prêté → 0 résultat.
    // -----------------------------------------------------------------------

    public function testFilterLoaned(): void
    {
        // Admin voit tous les équipements prêtés
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_COUNT, $this->countEquipmentRows($crawler));

        // Vérifier que c'est bien gakeB (Club B → emprunteur Club C)
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(AppFixtures::CLUB_B, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_C, $content);
    }

    public function testFilterLoanedAsMember(): void
    {
        // MEMBER (Club A, Région A) : seuls les équipements de Club A sont visibles,
        // et les régionaux de Région A disponibles. Club A n'a aucun équipement prêté → 0.
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // F. Combinaison type + status
    // -----------------------------------------------------------------------

    public function testFilterYumiAvailable(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterYumiLoaned(): void
    {
        // yumiD a un borrowerMember → 1 arc prêté
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterGakeAvailable(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'gake', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_GAKE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterGakeLoaned(): void
    {
        // gakeB (Club B → emprunté par Club C) + gakeNatBorrowed + gakeRegABorrowed → 3 gants prêtés
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'gake', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_GAKE_COUNT, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::CLUB_B, (string) $this->client->getResponse()->getContent());
    }

    // -----------------------------------------------------------------------
    // G. Recherche q sans filtre de type (DefaultSearchStrategy)
    //    Indexe : ownerClub.name, borrowerClub.name, CONCAT(id,'')
    //    Les équipements régionaux/nationaux ne sont pas trouvables par nom de propriétaire.
    // -----------------------------------------------------------------------

    public function testSearchByClubAName(): void
    {
        // Admin : "paris" → Club A + Club D + gakeRegABorrowed (borrowerClub=Club A)
        // gakeA1, gakeA2, yumiA1, yumiA2, yumiD → 5 + gakeRegABorrowed → 6
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'paris']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(6, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::CLUB_A, (string) $this->client->getResponse()->getContent());
    }

    public function testSearchByClubANameAsMember(): void
    {
        // MEMBER (Club A, Région A) : voit uniquement Club A + régionaux dispo de Région A.
        // "paris" → Club A : gakeA1, gakeA2, yumiA1, yumiA2 → 4
        // (yumiD=Club D non visible ; gakeRegABorrowed=régional emprunté non visible)
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'paris']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::CLUB_A, (string) $this->client->getResponse()->getContent());
    }

    public function testSearchByClubBName(): void
    {
        // "lyon" → Club B ("Kyudo Lyon") via owner.name → gakeB (1 résultat)
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'lyon']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::CLUB_B, (string) $this->client->getResponse()->getContent());
    }

    public function testSearchByBorrowerNameMatchesOwnerAndBorrower(): void
    {
        // "vincen" → Club C ("Kyudo Vincennes")
        //   via owner.name : gakeC (propriétaire = Club C)
        //   via borrower.name : gakeB (emprunté par Club C)
        // → 2 résultats
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'vincen']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    public function testSearchByOwnerRegionName(): void
    {
        // "ile" → Région A ("Ile de France") → gakeRA (owner), gakeRegABorrowed (owner) → 2
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'ile']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::REGION_A, (string) $this->client->getResponse()->getContent());
    }

    public function testSearchByOwnerRegionNameArc(): void
    {
        // "arc" → Région C ("Arc Atlantique") → gakeRC (owner) → 1
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'arc']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::REGION_C, (string) $this->client->getResponse()->getContent());
    }

    public function testSearchWithNoMatchReturnsNoResults(): void
    {
        // "bambou" ne correspond à aucun nom de club/région/fédération
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'bambou']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString('Aucun équipement trouvé', (string) $this->client->getResponse()->getContent());
    }

    // -----------------------------------------------------------------------
    // H. Recherche q + equipmentType=yumi (YumiSearchStrategy)
    //    Indexe : material, strength, yumiLength, ownerClub.name, borrowerClub.name
    //    Les équipements régionaux/nationaux sont trouvables via material/strength/length.
    // -----------------------------------------------------------------------

    public function testSearchByMaterialBambouInYumi(): void
    {
        // yumiA1 (Club A), yumiD (Club D), yumiRB1 (Rég. B), yumiNat1 (Fédé.) → 4
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByMaterialCarboneInYumi(): void
    {
        // yumiA2, yumiG, yumiRB2, yumiNat2 → 4
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'carbone', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByYumiLengthNamisun(): void
    {
        // yumiA1 (Club A), yumiE (Club E), yumiRB1 (Rég. B), yumiNat1 (Fédé.) → 4
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'namisun', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByYumiLengthNisunNobi(): void
    {
        // yumiA2, yumiG, yumiNat2 → 3
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'nisun_nobi', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testSearchByStrengthInYumi(): void
    {
        // strength LIKE %14% → yumiA1 (14), yumiNat2 (14) → 2
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => '14', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // I. Recherche q + equipmentType=gake (GakeSearchStrategy)
    //    Indexe : nb_fingers, size, ownerClub.name, borrowerClub.name
    // -----------------------------------------------------------------------

    public function testSearchByNbFingersInGake(): void
    {
        // nb_fingers LIKE %5% → gakeNat (nb_fingers=5) → 1
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => '5', 'equipmentType' => 'gake']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString('National', (string) $this->client->getResponse()->getContent());
    }

    public function testSearchByNbFingers3MatchesSixGakes(): void
    {
        // nb_fingers LIKE %3% → 7 gants avec nb_fingers=3
        // (gakeA1, gakeA2, gakeB, gakeC, gakeRA, gakeRC, gakeNatBorrowed — sauf gakeG(4), gakeNat(5), gakeRegABorrowed(4))
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => '3', 'equipmentType' => 'gake']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(7, $this->countEquipmentRows($crawler));
    }

    public function testSearchByOwnerNameInGake(): void
    {
        // "paris" → Club A → gakeA1, gakeA2 (owner)
        // + gakeRegABorrowed (borrowerClub=Club A → "Kyudo Paris Marais") → 3
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'paris', 'equipmentType' => 'gake']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testSearchByBorrowerNameInGakeMatchesBoth(): void
    {
        // "vincen" → gakeC (propriétaire = Club C) + gakeB (emprunteur = Club C) → 2
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'vincen', 'equipmentType' => 'gake']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // J. Combinaisons q + type + status
    // -----------------------------------------------------------------------

    public function testCombinedBambouYumiAvailable(): void
    {
        // bambou + yumi + available :
        // yumiA1, yumiRB1, yumiNat1 → 3 (yumiD a un borrowerMember → loaned)
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testCombinedBambouYumiLoaned(): void
    {
        // bambou + yumi + loaned : yumiD (bambou, borrowerMember) → 1
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
    }

    public function testCombinedGakeLoanedWithNoQuery(): void
    {
        // gake + loaned (sans q) → gakeB + gakeNatBorrowed + gakeRegABorrowed → 3
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'gake', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::CLUB_B, (string) $this->client->getResponse()->getContent());
    }

    public function testCombinedParisYumiNoStatus(): void
    {
        // "paris" + yumi → Club A ("Kyudo Paris Marais") → yumiA1, yumiA2
        // Club D ("Ryushin Dojo Paris") → yumiD → total 3
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'paris', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // K. Filtre borrowed={userId} — équipements empruntés par un utilisateur
    // -----------------------------------------------------------------------

    public function testBorrowedFilterShowsOnlyBorrowedByUser(): void
    {
        // USER_MEMBER (Jean Dupont) emprunte gakeNatBorrowed + yumiD → 2
        // En tant qu'admin (voit tout), le filtre doit retourner exactement 2
        $this->loginAs(AppFixtures::USER_ADMIN);

        /** @var \App\Repository\UserRepository $repo */
        $repo = self::getContainer()->get(\App\Repository\UserRepository::class);
        $memberUser = $repo->findOneBy(['email' => AppFixtures::USER_MEMBER]);
        $this->assertInstanceOf(\App\Entity\User::class, $memberUser);
        $crawler = $this->requestIndex(['borrowed' => (string) $memberUser->getId()]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    public function testBorrowedFilterCombinedWithEquipmentType(): void
    {
        // USER_MEMBER emprunte gakeNatBorrowed (gake) + yumiD (yumi)
        // Filtre borrowed + equipmentType=yumi → 1 seul (yumiD)
        $this->loginAs(AppFixtures::USER_ADMIN);

        /** @var \App\Repository\UserRepository $repo */
        $repo = self::getContainer()->get(\App\Repository\UserRepository::class);
        $memberUser = $repo->findOneBy(['email' => AppFixtures::USER_MEMBER]);
        $this->assertInstanceOf(\App\Entity\User::class, $memberUser);
        $crawler = $this->requestIndex(['borrowed' => (string) $memberUser->getId(), 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
    }

    public function testBorrowedFilterWithUserWithNoBorrowsShowsNothing(): void
    {
        // USER_ADMIN n'emprunte rien → 0 résultat
        $this->loginAs(AppFixtures::USER_ADMIN);

        /** @var \App\Repository\UserRepository $repo */
        $repo = self::getContainer()->get(\App\Repository\UserRepository::class);
        $adminUser = $repo->findOneBy(['email' => AppFixtures::USER_ADMIN]);
        $this->assertInstanceOf(\App\Entity\User::class, $adminUser);
        $crawler = $this->requestIndex(['borrowed' => (string) $adminUser->getId()]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $this->countEquipmentRows($crawler));
    }

    public function testBorrowedFilterWithInvalidUserIdShowsNothing(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['borrowed' => '999999']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $this->countEquipmentRows($crawler));
    }
}
