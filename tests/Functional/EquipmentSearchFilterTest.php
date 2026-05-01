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
 *   gloveA1          CLUB      Club A (Paris Marais)    nb_fingers=3  size=8   disponible
 *   gloveA2          CLUB      Club A (Paris Marais)    nb_fingers=3  size=7   disponible
 *   gloveB           CLUB      Club B (Lyon)            nb_fingers=3  size=9   prêté à Club C
 *   gloveC           CLUB      Club C (Vincennes)       nb_fingers=3  size=6   disponible
 *   gloveG           CLUB      Club G (Rennes)          nb_fingers=4  size=7   disponible
 *   gloveRA          REGIONAL  Région A (Île-de-France) nb_fingers=3  size=8   disponible
 *   gloveRC          REGIONAL  Région C (Bretagne)      nb_fingers=3  size=7   disponible
 *   gloveNat         NATIONAL  Fédération               nb_fingers=5  size=10  disponible
 *   gloveNatBorrowed NATIONAL  Fédération               nb_fingers=3  size=8   prêté à memberLinked
 *   gloveRegABorrowed REGIONAL Région A (Île-de-France) nb_fingers=4  size=7   prêté à Club A
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
 * equipmentType=glove     → 10
 *
 * status=all              → 19
 * status=available        → 15  (gloveB + yumiD + gloveNatBorrowed + gloveRegABorrowed → 4 prêtés)
 * status=loaned           →  4  (gloveB + yumiD + gloveNatBorrowed + gloveRegABorrowed)
 *
 * status=available + yumi →  8  (yumiD a un borrowerMember → loaned)
 * status=loaned   + yumi  →  1  (yumiD)
 * status=available + glove→  7  (tous les gloves sauf gloveB, gloveNatBorrowed, gloveRegABorrowed)
 * status=loaned   + glove →  3  (gloveB + gloveNatBorrowed + gloveRegABorrowed)
 *
 * Recherche textuelle (DefaultSearchStrategy — sans filtre de type) :
 *   Champs indexés : ownerClub.name, borrowerClub.name, CONCAT(id,'')
 *   Les équipements régionaux/nationaux ont ownerClub NULL → non trouvables par nom de propriétaire.
 *
 *   q="paris"   → Club A ("Kyudo Paris Marais") + Club D ("Ryushin Dojo Paris")
 *                  gloveA1, gloveA2, yumiA1, yumiA2, yumiD → 5
 *                  + gloveRegABorrowed (borrowerClub=Club A → "Paris") → 6
 *   q="lyon"    → Club B ("Kyudo Lyon") → gloveB (via owner.name) → 1
 *   q="vincen"  → Club C ("Kyudo Vincennes") via owner.name (gloveC) + via borrower.name (gloveB) → 2
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
 * Recherche textuelle (GloveSearchStrategy — type=glove) :
 *   Champs indexés : nb_fingers, size, ownerClub.name, borrowerClub.name
 *   Équipements régionaux/nationaux trouvables via nb_fingers/size.
 *
 *   q="5"      → gloveNat (nb_fingers=5) → 1
 *   q="3"      → 7 gloves avec nb_fingers=3 (gloveA1,gloveA2,gloveB,gloveC,gloveRA,gloveRC,gloveNatBorrowed)
 *   q="paris"  → Club A → gloveA1, gloveA2 + gloveRegABorrowed (borrowerClub=Club A) → 3
 *   q="vincen" → Club C via owner.name (gloveC) + via borrower.name (gloveB) → 2
 */
final class EquipmentSearchFilterTest extends AbstractWebTestCase
{
    // ─── Totaux globaux ──────────────────────────────────────────────────────
    private const int TOTAL_COUNT = 19;

    private const int TOTAL_YUMI_COUNT = 9;

    private const int TOTAL_GLOVE_COUNT = 10;

    // ─── Totaux par status ───────────────────────────────────────────────────

    /** gloveB (borrowerClub) + yumiD (borrowerMember) + gloveNatBorrowed + gloveRegABorrowed → 4 prêtés */
    private const int LOANED_COUNT = 4;

    private const int AVAILABLE_COUNT = 15;

    private const int AVAILABLE_YUMI_COUNT = 8;

    private const int LOANED_YUMI_COUNT = 1;

    private const int AVAILABLE_GLOVE_COUNT = 7;

    private const int LOANED_GLOVE_COUNT = 3;

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
    // C. Filtre equipmentType=glove
    // -----------------------------------------------------------------------

    public function testFilterByGloveType(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // D. Filtre status=available
    // Note : le filtre vérifie borrowerClub IS NULL ET borrowerMember IS NULL.
    // gloveB (borrowerClub) + yumiD (borrowerMember) → 2 prêtés, 15 disponibles.
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
    // Admin : gloveB + yumiD + gloveNatBorrowed + gloveRegABorrowed → LOANED_COUNT=4.
    // Member (Club A / Île-de-France) : ne voit que son club + régional dispo →
    //   Club A n'a aucun équipement prêté → 0 résultat.
    // -----------------------------------------------------------------------

    public function testFilterLoaned(): void
    {
        // Admin voit tous les équipements prêtés
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_COUNT, $this->countEquipmentRows($crawler));

        // Vérifier que c'est bien gloveB (Club B → emprunteur Club C)
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

    public function testFilterGloveAvailable(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterGloveLoaned(): void
    {
        // gloveB (Club B → emprunté par Club C) + gloveNatBorrowed + gloveRegABorrowed → 3 gants prêtés
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_GLOVE_COUNT, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::CLUB_B, (string) $this->client->getResponse()->getContent());
    }

    // -----------------------------------------------------------------------
    // G. Recherche q sans filtre de type (DefaultSearchStrategy)
    //    Indexe : ownerClub.name, borrowerClub.name, CONCAT(id,'')
    //    Les équipements régionaux/nationaux ne sont pas trouvables par nom de propriétaire.
    // -----------------------------------------------------------------------

    public function testSearchByClubAName(): void
    {
        // Admin : "paris" → Club A + Club D + gloveRegABorrowed (borrowerClub=Club A)
        // gloveA1, gloveA2, yumiA1, yumiA2, yumiD → 5 + gloveRegABorrowed → 6
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'paris']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(6, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::CLUB_A, (string) $this->client->getResponse()->getContent());
    }

    public function testSearchByClubANameAsMember(): void
    {
        // MEMBER (Club A, Région A) : voit uniquement Club A + régionaux dispo de Région A.
        // "paris" → Club A : gloveA1, gloveA2, yumiA1, yumiA2 → 4
        // (yumiD=Club D non visible ; gloveRegABorrowed=régional emprunté non visible)
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'paris']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::CLUB_A, (string) $this->client->getResponse()->getContent());
    }

    public function testSearchByClubBName(): void
    {
        // "lyon" → Club B ("Kyudo Lyon") via owner.name → gloveB (1 résultat)
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'lyon']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::CLUB_B, (string) $this->client->getResponse()->getContent());
    }

    public function testSearchByBorrowerNameMatchesOwnerAndBorrower(): void
    {
        // "vincen" → Club C ("Kyudo Vincennes")
        //   via owner.name : gloveC (propriétaire = Club C)
        //   via borrower.name : gloveB (emprunté par Club C)
        // → 2 résultats
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'vincen']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
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
    // I. Recherche q + equipmentType=glove (GloveSearchStrategy)
    //    Indexe : nb_fingers, size, ownerClub.name, borrowerClub.name
    // -----------------------------------------------------------------------

    public function testSearchByNbFingersInGlove(): void
    {
        // nb_fingers LIKE %5% → gloveNat (nb_fingers=5) → 1
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => '5', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString('National', (string) $this->client->getResponse()->getContent());
    }

    public function testSearchByNbFingers3MatchesSixGloves(): void
    {
        // nb_fingers LIKE %3% → 7 gants avec nb_fingers=3
        // (gloveA1, gloveA2, gloveB, gloveC, gloveRA, gloveRC, gloveNatBorrowed — sauf gloveG(4), gloveNat(5), gloveRegABorrowed(4))
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => '3', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(7, $this->countEquipmentRows($crawler));
    }

    public function testSearchByOwnerNameInGlove(): void
    {
        // "paris" → Club A → gloveA1, gloveA2 (owner)
        // + gloveRegABorrowed (borrowerClub=Club A → "Kyudo Paris Marais") → 3
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'paris', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testSearchByBorrowerNameInGloveMatchesBoth(): void
    {
        // "vincen" → gloveC (propriétaire = Club C) + gloveB (emprunteur = Club C) → 2
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'vincen', 'equipmentType' => 'glove']);
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

    public function testCombinedGloveLoanedWithNoQuery(): void
    {
        // glove + loaned (sans q) → gloveB + gloveNatBorrowed + gloveRegABorrowed → 3
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'loaned']);
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
}
