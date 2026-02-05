<?php

namespace App\Tests\Admin;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\NpcProfile;
use App\Entity\User;
use App\Entity\World;
use App\Game\Domain\Npc\NpcArchetype;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminCharacterPageEventHistoryTest extends WebTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCharacterPageShowsWorldRowDataAndEventHistory(): void
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

        $world     = new World('seed-1');
        $character = new Character($world, 'Goku', Race::Saiyan);
        $character->setEmployment('farmer', 1, 2);
        $character->setStrength(10);
        $character->setKiControl(10);
        $character->setKiCapacity(10);

        $profile = new NpcProfile($character, NpcArchetype::Fighter);
        $event1  = new CharacterEvent($world, $character, 'trained', 1, ['stat' => 'strength', 'delta' => 1]);
        $event2  = new CharacterEvent($world, $character, 'money_low_employed', 2, ['money' => 0]);

        $entityManager->persist($admin);
        $entityManager->persist($world);
        $entityManager->persist($character);
        $entityManager->persist($profile);
        $entityManager->persist($event1);
        $entityManager->persist($event2);
        $entityManager->flush();

        $client->loginUser($admin, 'main');
        $client->request('GET', sprintf('/admin/characters/%d', (int)$character->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Goku');

        self::assertSelectorTextContains('body', 'Powerlevel');
        self::assertSelectorTextContains('body', 'Job');
        self::assertSelectorTextContains('body', 'Archetype');

        self::assertSelectorTextContains('body', 'trained');
        self::assertSelectorTextContains('body', 'money_low_employed');
    }
}

