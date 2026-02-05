<?php

namespace App\Tests\Admin;

use App\Entity\Character;
use App\Entity\NpcProfile;
use App\Entity\User;
use App\Entity\World;
use App\Game\Domain\Race;
use App\Game\Domain\Npc\NpcArchetype;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminWorldCharactersSortTest extends WebTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCharactersTableHasPowerlevelColumnAndCanSortByIt(): void
    {
        $client = self::createClient();

        $container     = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get('security.user_password_hasher');
        $admin          = (new User())
            ->setUsername('admin')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, 'password'));

        $world = new World('seed-1');
        $weak  = new Character($world, 'Krillin', Race::Human);
        $weak->setStrength(2);
        $weak->setKiControl(2);

        $strong = new Character($world, 'Goku', Race::Saiyan);
        $strong->setStrength(10);
        $strong->setKiControl(10);
        $strong->setKiCapacity(10);
        $strong->setEmployment('farmer', 1, 2);

        $profile = new NpcProfile($strong, NpcArchetype::Fighter);

        $entityManager->persist($admin);
        $entityManager->persist($world);
        $entityManager->persist($weak);
        $entityManager->persist($strong);
        $entityManager->persist($profile);
        $entityManager->flush();

        $client->loginUser($admin, 'main');
        $client->request('GET', sprintf('/admin/worlds/%d?sort=powerlevel&dir=desc', (int)$world->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table thead tr', 'Powerlevel');
        self::assertSelectorTextContains('table thead tr', 'Job');
        self::assertSelectorTextContains('table thead tr', 'Archetype');
        self::assertSelectorTextContains('table tbody tr:first-child td:nth-child(2)', 'Goku');
    }
}
