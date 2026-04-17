<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Address;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use App\DataFixtures\AppFixtures;

/**
 * Tests fonctionnels : AddressController.
 *
 * Routes testées :
 *   GET  /address/{id}         → IS_AUTHENTICATED_FULLY
 *   GET  /address/new          → CREATE_ADDRESS
 *   GET  /address/{id}/edit    → EDIT_ADDRESS
 *   POST /address/{id}/delete  → DELETE_ADDRESS
 *
 * Matrice des droits attendus :
 * ┌────────────────────┬───────┬────────┬──────────┬──────────────┬─────────────┬────────────┬───────┐
 * │ Action             │ USER  │ MEMBER │ PRESIDENT│ MGMT_CLUB    │ MGMT_CTK    │ MGMT_CN    │ ADMIN │
 * ├────────────────────┼───────┼────────┼──────────┼──────────────┼─────────────┼────────────┼───────┤
 * │ show  (GET)        │  200  │  200   │  200     │  200         │  200        │  200       │  200  │
 * │ new   (GET)        │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * │ edit  (GET)        │  403  │  403   │  403     │  403         │  200        │  200       │  200  │
 * │ delete (POST)      │  403  │  403   │  403     │  403         │  403        │  200       │  200  │
 * └────────────────────┴───────┴────────┴──────────┴──────────────┴─────────────┴────────────┴───────┘
 *
 * Note : show utilise IS_AUTHENTICATED_FULLY → tout utilisateur connecté y a accès.
 */
final class AddressControllerTest extends AbstractWebTestCase
{
    private int $addressId;

    protected function setUp(): void
    {
        parent::setUp();

        // Crée une adresse directement en base pour les tests qui en ont besoin
        $container = self::getContainer();
        /** @var ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var EntityManagerInterface $objectManager */
        $objectManager = $doctrine->getManager();

        $address = (new Address())
            ->setPostalCode('75001')
            ->setCity('Paris')
            ->setCountry('France');
        $objectManager->persist($address);
        $objectManager->flush();

        $this->addressId = $address->getId();
    }

    // -----------------------------------------------------------------------
    // GET /address/{id} — show
    // -----------------------------------------------------------------------
    public function testShowGrantedForRoleUser(): void
    {
        // IS_AUTHENTICATED_FULLY : ROLE_USER est authentifié → accès accordé
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetGranted('/address/'.$this->addressId);
    }

    public function testShowGrantedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetGranted('/address/'.$this->addressId);
    }

    public function testShowGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/address/'.$this->addressId);
    }

    // -----------------------------------------------------------------------
    // GET /address/new — création
    // -----------------------------------------------------------------------
    public function testNewDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/address/new');
    }

    public function testNewDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/address/new');
    }

    public function testNewDeniedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/address/new');
    }

    public function testNewDeniedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/address/new');
    }

    public function testNewGrantedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/address/new');
    }

    public function testNewGrantedForEquipmentManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->assertGetGranted('/address/new');
    }

    public function testNewGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/address/new');
    }

    // -----------------------------------------------------------------------
    // GET /address/{id}/edit — édition
    // -----------------------------------------------------------------------
    public function testEditDeniedForRoleUser(): void
    {
        $this->loginAs(AppFixtures::USER_USER);
        $this->assertGetDenied('/address/'.$this->addressId.'/edit');
    }

    public function testEditDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->assertGetDenied('/address/'.$this->addressId.'/edit');
    }

    public function testEditDeniedForPresident(): void
    {
        $this->loginAs(AppFixtures::USER_PRESIDENT);
        $this->assertGetDenied('/address/'.$this->addressId.'/edit');
    }

    public function testEditDeniedForEquipmentManagerClub(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CLUB);
        $this->assertGetDenied('/address/'.$this->addressId.'/edit');
    }

    public function testEditGrantedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->assertGetGranted('/address/'.$this->addressId.'/edit');
    }

    public function testEditGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->assertGetGranted('/address/'.$this->addressId.'/edit');
    }

    // -----------------------------------------------------------------------
    // POST /address/{id}/delete — suppression
    // Note : on teste avec POST + CSRF bidon → le contrôleur vérifie d'abord
    //        les droits (403 avant validation CSRF)
    // -----------------------------------------------------------------------
    public function testDeleteDeniedForMember(): void
    {
        $this->loginAs(AppFixtures::USER_MEMBER);
        $this->client->request(Request::METHOD_POST, '/address/'.$this->addressId, [
            '_token' => 'invalid_token',
        ]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
    }

    public function testDeleteDeniedForEquipmentManagerCtk(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CTK);
        $this->client->request(Request::METHOD_POST, '/address/'.$this->addressId, [
            '_token' => 'invalid_token',
        ]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
    }

    public function testDeleteGrantedForEquipmentManagerCn(): void
    {
        $this->loginAs(AppFixtures::USER_MANAGER_CN);
        $this->client->request(Request::METHOD_POST, '/address/'.$this->addressId, [
            '_method' => 'DELETE',
            '_token' => 'invalid_token', // le CSRF sera invalide mais le droit est accordé → 302 ou 419
        ]);
        // Accès accordé : le contrôleur tente la suppression (même si CSRF invalide → 302 redirect ou 419)
        // Dans tous les cas, ce ne doit PAS être 403
        $this->assertNotSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testDeleteGrantedForAdmin(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);
        $this->client->request(Request::METHOD_POST, '/address/'.$this->addressId, [
            '_method' => 'DELETE',
            '_token' => 'invalid_token',
        ]);
        $this->assertNotSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }
}
