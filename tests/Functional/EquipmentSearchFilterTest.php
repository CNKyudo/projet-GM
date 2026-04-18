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
 * les bons équipements pour CHAQUE rôle.
 *
 * Tous les rôles autorisés (MEMBER et au-dessus) voient l'intégralité
 * des équipements — les filtres réduisent ce jeu de façon identique
 * quel que soit le rôle.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * INVENTAIRE DES FIXTURES (17 équipements au total)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Gants (8) :
 *   gloveA1  CLUB      Club A (Paris Marais)    nb_fingers=3  size=8   disponible
 *   gloveA2  CLUB      Club A (Paris Marais)    nb_fingers=3  size=7   disponible
 *   gloveB   CLUB      Club B (Lyon)            nb_fingers=3  size=9   prêté à Club C
 *   gloveC   CLUB      Club C (Vincennes)       nb_fingers=3  size=6   disponible
 *   gloveG   CLUB      Club G (Rennes)          nb_fingers=4  size=7   disponible
 *   gloveRA  REGIONAL  Région A (Île-de-France) nb_fingers=3  size=8   disponible
 *   gloveRC  REGIONAL  Région C (Bretagne)      nb_fingers=3  size=7   disponible
 *   gloveNat NATIONAL  Fédération               nb_fingers=5  size=10  disponible
 *
 * Arcs (9) :
 *   yumiA1   CLUB      Club A  bambou       14  namisun      disponible
 *   yumiA2   CLUB      Club A  carbone      12  nisun_nobi   disponible
 *   yumiD    CLUB      Club D  bambou       16  yonsun_nobi  emprunteur=USER_MEMBER (pas borrower_club → "disponible" pour le filtre status)
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
 * Sans filtre             → 17
 * equipmentType=yumi      →  9
 * equipmentType=glove     →  8
 *
 * status=all              → 17
 * status=available        → 16  (gloveB a un borrower_club → "prêté" ; yumiD a borrower_user mais pas borrower_club → "disponible")
 * status=loaned           →  1  (gloveB uniquement)
 *
 * status=available + yumi →  9  (aucun yumi n'a de borrower_club)
 * status=loaned   + yumi  →  0
 * status=available + glove→  7  (tous les gloves sauf gloveB)
 * status=loaned   + glove →  1  (gloveB)
 *
 * Recherche textuelle (DefaultSearchStrategy — sans filtre de type) :
 *   Champs indexés : owner_club.name, borrower_club.name, CONCAT(id,'')
 *   Les équipements régionaux/nationaux ont owner_club NULL → non trouvables par nom de propriétaire.
 *
 *   q="paris"   → Club A ("Kyudo Paris Marais") + Club D ("Ryushin Dojo Paris")
 *                  gloveA1, gloveA2, yumiA1, yumiA2, yumiD → 5
 *   q="lyon"    → Club B ("Kyudo Lyon") → gloveB (via owner.name) → 1
 *   q="vincen"  → Club C ("Kyudo Vincennes") via owner.name (gloveC) + via borrower.name (gloveB) → 2
 *   q="bambou"  → aucun club/région/fédération ne contient "bambou" → 0
 *
 * Recherche textuelle (YumiSearchStrategy — type=yumi) :
 *   Champs indexés : material, strength, yumiLength, owner_club.name, borrower_club.name
 *   Équipements régionaux/nationaux trouvables via material/strength/length.
 *
 *   q="bambou"     → yumiA1, yumiD, yumiRB1, yumiNat1 → 4
 *   q="carbone"    → yumiA2, yumiG, yumiRB2, yumiNat2 → 4
 *   q="namisun"    → yumiA1, yumiE, yumiRB1, yumiNat1 → 4
 *   q="nisun_nobi" → yumiA2, yumiG, yumiNat2           → 3
 *   q="14"         → yumiA1 (strength=14), yumiNat2 (strength=14) → 2
 *
 * Recherche textuelle (GloveSearchStrategy — type=glove) :
 *   Champs indexés : nb_fingers, size, owner_club.name, borrower_club.name
 *   Équipements régionaux/nationaux trouvables via nb_fingers/size.
 *
 *   q="5"      → gloveNat (nb_fingers=5) → 1
 *   q="3"      → 6 gloves avec nb_fingers=3 (tous sauf gloveG et gloveNat) → 6
 *   q="paris"  → Club A → gloveA1, gloveA2 → 2
 *   q="vincen" → Club C via owner.name (gloveC) + via borrower.name (gloveB) → 2
 */
final class EquipmentSearchFilterTest extends AbstractWebTestCase
{
    // ─── Totaux globaux ──────────────────────────────────────────────────────
    private const int TOTAL_COUNT = 17;

    private const int TOTAL_YUMI_COUNT = 9;

    private const int TOTAL_GLOVE_COUNT = 8;

    // ─── Totaux par status ───────────────────────────────────────────────────

    /** gloveB (borrower_club) + yumiD (borrower_user) → 2 prêtés */
    private const int LOANED_COUNT = 2;

    private const int AVAILABLE_COUNT = 15;

    private const int AVAILABLE_YUMI_COUNT = 8;

    private const int LOANED_YUMI_COUNT = 1;

    private const int AVAILABLE_GLOVE_COUNT = 7;

    private const int LOANED_GLOVE_COUNT = 1;

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
    // A. Aucun filtre — tous les rôles voient les 17 équipements
    // -----------------------------------------------------------------------

    public function testNoFilterShowsAllEquipmentsForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex();
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testNoFilterShowsAllEquipmentsForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $crawler = $this->requestIndex();
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testNoFilterShowsAllEquipmentsForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex();
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testNoFilterShowsAllEquipmentsForManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $crawler = $this->requestIndex();
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testNoFilterShowsAllEquipmentsForManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $crawler = $this->requestIndex();
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testNoFilterShowsAllEquipmentsForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex();
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_COUNT, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // B. Filtre equipmentType=yumi
    // -----------------------------------------------------------------------

    public function testFilterByYumiTypeForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterByYumiTypeForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterByYumiTypeForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterByYumiTypeForManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterByYumiTypeForManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterByYumiTypeForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // C. Filtre equipmentType=glove
    // -----------------------------------------------------------------------

    public function testFilterByGloveTypeForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterByGloveTypeForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $crawler = $this->requestIndex(['equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterByGloveTypeForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterByGloveTypeForManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $crawler = $this->requestIndex(['equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterByGloveTypeForManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $crawler = $this->requestIndex(['equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterByGloveTypeForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::TOTAL_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // D. Filtre status=available
    // Note : le filtre vérifie borrower_club IS NULL.
    // yumiD possède un borrower_user (pas un borrower_club) → compte comme "disponible".
    // -----------------------------------------------------------------------

    public function testFilterAvailableForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterAvailableForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $crawler = $this->requestIndex(['status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterAvailableForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterAvailableForManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $crawler = $this->requestIndex(['status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterAvailableForManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $crawler = $this->requestIndex(['status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterAvailableForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_COUNT, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // E. Filtre status=loaned
    // Seul gloveB (Club B → emprunté par Club C) a un borrower_club renseigné.
    // -----------------------------------------------------------------------

    public function testFilterLoanedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_COUNT, $this->countEquipmentRows($crawler));

        // Vérifier que c'est bien gloveB (Club B → emprunteur Club C)
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(AppFixtures::CLUB_B, $content);
        $this->assertStringContainsString(AppFixtures::CLUB_C, $content);
    }

    public function testFilterLoanedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $crawler = $this->requestIndex(['status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterLoanedForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterLoanedForManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $crawler = $this->requestIndex(['status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterLoanedForManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $crawler = $this->requestIndex(['status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterLoanedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_COUNT, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // F. Combinaison type + status
    // -----------------------------------------------------------------------

    public function testFilterYumiAvailableForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterYumiAvailableForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterYumiAvailableForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterYumiAvailableForManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterYumiAvailableForManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterYumiAvailableForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterYumiLoanedReturnsNothingForMember(): void
    {
        // yumiD a un borrower_user → 1 arc prêté
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterYumiLoanedReturnsNothingForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'yumi', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_YUMI_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterGloveAvailableForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterGloveAvailableForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterGloveAvailableForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterGloveAvailableForManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterGloveAvailableForManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterGloveAvailableForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::AVAILABLE_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterGloveLoanedForMember(): void
    {
        // gloveB (Club B → emprunté par Club C) est le seul gant prêté
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    public function testFilterGloveLoanedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(self::LOANED_GLOVE_COUNT, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // G. Recherche q sans filtre de type (DefaultSearchStrategy)
    //    Indexe : owner_club.name, borrower_club.name, CONCAT(id,'')
    //    Les équipements régionaux/nationaux ne sont pas trouvables par nom de propriétaire.
    // -----------------------------------------------------------------------

    public function testSearchByClubANameForMember(): void
    {
        // "paris" → Club A (Paris Marais) + Club D (Ryushin Dojo Paris)
        // gloveA1, gloveA2, yumiA1, yumiA2, yumiD → 5
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'paris']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(5, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::CLUB_A, (string) $this->client->getResponse()->getContent());
    }

    public function testSearchByClubANameForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $crawler = $this->requestIndex(['q' => 'paris']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(5, $this->countEquipmentRows($crawler));
    }

    public function testSearchByClubANameForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['q' => 'paris']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(5, $this->countEquipmentRows($crawler));
    }

    public function testSearchByClubANameForManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $crawler = $this->requestIndex(['q' => 'paris']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(5, $this->countEquipmentRows($crawler));
    }

    public function testSearchByClubANameForManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $crawler = $this->requestIndex(['q' => 'paris']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(5, $this->countEquipmentRows($crawler));
    }

    public function testSearchByClubANameForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'paris']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(5, $this->countEquipmentRows($crawler));
    }

    public function testSearchByClubBNameForAdmin(): void
    {
        // "lyon" → Club B ("Kyudo Lyon") via owner.name → gloveB (1 résultat)
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'lyon']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::CLUB_B, (string) $this->client->getResponse()->getContent());
    }

    public function testSearchByClubBNameForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'lyon']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
    }

    public function testSearchByBorrowerNameMatchesOwnerAndBorrowerForAdmin(): void
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

    public function testSearchByBorrowerNameMatchesOwnerAndBorrowerForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'vincen']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    public function testSearchWithNoMatchReturnsNoResultsForAdmin(): void
    {
        // "bambou" ne correspond à aucun nom de club/région/fédération
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'bambou']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString('Aucun équipement trouvé', (string) $this->client->getResponse()->getContent());
    }

    public function testSearchWithNoMatchReturnsNoResultsForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'bambou']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $this->countEquipmentRows($crawler));
    }

    public function testSearchWithNoMatchReturnsNoResultsForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['q' => 'bambou']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // H. Recherche q + equipmentType=yumi (YumiSearchStrategy)
    //    Indexe : material, strength, yumiLength, owner_club.name, borrower_club.name
    //    Les équipements régionaux/nationaux sont trouvables via material/strength/length.
    // -----------------------------------------------------------------------

    public function testSearchByMaterialBambouInYumiForMember(): void
    {
        // yumiA1 (Club A), yumiD (Club D), yumiRB1 (Rég. B), yumiNat1 (Fédé.) → 4
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByMaterialBambouInYumiForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByMaterialBambouInYumiForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByMaterialBambouInYumiForManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByMaterialBambouInYumiForManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByMaterialBambouInYumiForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByMaterialCarboneInYumiForAdmin(): void
    {
        // yumiA2, yumiG, yumiRB2, yumiNat2 → 4
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'carbone', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByMaterialCarboneInYumiForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'carbone', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByMaterialCarboneInYumiForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['q' => 'carbone', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByYumiLengthNamisunForAdmin(): void
    {
        // yumiA1 (Club A), yumiE (Club E), yumiRB1 (Rég. B), yumiNat1 (Fédé.) → 4
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'namisun', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByYumiLengthNamisunForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'namisun', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByYumiLengthNamisunForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['q' => 'namisun', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(4, $this->countEquipmentRows($crawler));
    }

    public function testSearchByYumiLengthNisunNobiForAdmin(): void
    {
        // yumiA2, yumiG, yumiNat2 → 3
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'nisun_nobi', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testSearchByYumiLengthNisunNobiForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'nisun_nobi', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testSearchByStrengthInYumiForAdmin(): void
    {
        // strength LIKE %14% → yumiA1 (14), yumiNat2 (14) → 2
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => '14', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    public function testSearchByStrengthInYumiForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => '14', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    public function testSearchByStrengthInYumiForManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $crawler = $this->requestIndex(['q' => '14', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // I. Recherche q + equipmentType=glove (GloveSearchStrategy)
    //    Indexe : nb_fingers, size, owner_club.name, borrower_club.name
    // -----------------------------------------------------------------------

    public function testSearchByNbFingersInGloveForAdmin(): void
    {
        // nb_fingers LIKE %5% → gloveNat (nb_fingers=5) → 1
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => '5', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString('National', (string) $this->client->getResponse()->getContent());
    }

    public function testSearchByNbFingersInGloveForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => '5', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
    }

    public function testSearchByNbFingersInGloveForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['q' => '5', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
    }

    public function testSearchByNbFingers3MatchesSixGlovesForAdmin(): void
    {
        // nb_fingers LIKE %3% → 6 gants avec nb_fingers=3 (tous sauf gloveG(4) et gloveNat(5))
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => '3', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(6, $this->countEquipmentRows($crawler));
    }

    public function testSearchByNbFingers3MatchesSixGlovesForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => '3', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(6, $this->countEquipmentRows($crawler));
    }

    public function testSearchByNbFingers3MatchesSixGlovesForManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $crawler = $this->requestIndex(['q' => '3', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(6, $this->countEquipmentRows($crawler));
    }

    public function testSearchByOwnerNameInGloveForAdmin(): void
    {
        // "paris" → Club A → gloveA1, gloveA2 → 2 gants (Club D n'a pas de gant)
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'paris', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    public function testSearchByOwnerNameInGloveForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'paris', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    public function testSearchByOwnerNameInGloveForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['q' => 'paris', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    public function testSearchByBorrowerNameInGloveMatchesBothForAdmin(): void
    {
        // "vincen" → gloveC (propriétaire = Club C) + gloveB (emprunteur = Club C) → 2
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'vincen', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    public function testSearchByBorrowerNameInGloveMatchesBothForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'vincen', 'equipmentType' => 'glove']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countEquipmentRows($crawler));
    }

    // -----------------------------------------------------------------------
    // J. Combinaisons q + type + status
    // -----------------------------------------------------------------------

    public function testCombinedBambouYumiAvailableForAdmin(): void
    {
        // bambou + yumi + available :
        // yumiA1, yumiRB1, yumiNat1 → 3 (yumiD a un borrower_user → loaned)
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testCombinedBambouYumiAvailableForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testCombinedBambouYumiAvailableForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testCombinedBambouYumiAvailableForManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testCombinedBambouYumiAvailableForManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testCombinedBambouYumiAvailableForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi', 'status' => 'available']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testCombinedBambouYumiLoanedReturnsNothingForAdmin(): void
    {
        // bambou + yumi + loaned : yumiD (bambou, borrower_user) → 1
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
    }

    public function testCombinedBambouYumiLoanedReturnsNothingForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'bambou', 'equipmentType' => 'yumi', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
    }

    public function testCombinedGloveLoanedWithNoQueryForAdmin(): void
    {
        // glove + loaned (sans q) → gloveB uniquement → 1
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
        $this->assertStringContainsString(AppFixtures::CLUB_B, (string) $this->client->getResponse()->getContent());
    }

    public function testCombinedGloveLoanedWithNoQueryForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['equipmentType' => 'glove', 'status' => 'loaned']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countEquipmentRows($crawler));
    }

    public function testCombinedPariYumiNoStatusForAdmin(): void
    {
        // "paris" + yumi → Club A ("Kyudo Paris Marais") → yumiA1, yumiA2
        // Club D ("Ryushin Dojo Paris") → yumiD → total 3
        $this->loginAs(AppFixtures::USER_ADMIN);
        $crawler = $this->requestIndex(['q' => 'paris', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testCombinedPariYumiNoStatusForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $crawler = $this->requestIndex(['q' => 'paris', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }

    public function testCombinedPariYumiNoStatusForManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $crawler = $this->requestIndex(['q' => 'paris', 'equipmentType' => 'yumi']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(3, $this->countEquipmentRows($crawler));
    }
}
