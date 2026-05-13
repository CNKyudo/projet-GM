<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Equipment;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class EquipmentVersioningTest extends AbstractWebTestCase
{
    public function testUsernameColumnStoresUserIdInsteadOfEmail(): void
    {
        $this->loginAs(AppFixtures::USER_ADMIN);

        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $equipment = $em->createQueryBuilder()
            ->select('e')
            ->from(Equipment::class, 'e')
            ->join('e.ownerClub', 'c')
            ->where('c.name = :clubName')
            ->setParameter('clubName', AppFixtures::CLUB_A)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult();
        $this->assertInstanceOf(Equipment::class, $equipment);

        $equipment->setNotes('test versioning via direct EM');
        $em->flush();

        $conn = $em->getConnection();
        $logEntries = $conn->fetchAllAssociative(
            'SELECT username FROM ext_log_entries WHERE object_id = :id AND object_class LIKE :class AND action = :action',
            ['id' => (string) $equipment->getId(), 'class' => '%Glove%', 'action' => 'update'],
        );

        $this->assertNotEmpty($logEntries, 'No log entry found for the edited equipment');

        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        $admin = $userRepo->findOneBy(['email' => AppFixtures::USER_ADMIN]);
        $this->assertInstanceOf(\App\Entity\User::class, $admin);
        $adminId = (string) $admin->getId();

        $this->assertSame($adminId, $logEntries[0]['username'], 'Username should be the user ID, not the email');
        $this->assertNotSame(AppFixtures::USER_ADMIN, $logEntries[0]['username'], 'Username should NOT be the email');
    }
}
