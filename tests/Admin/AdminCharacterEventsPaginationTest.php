<?php

namespace App\Tests\Admin;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\User;
use App\Entity\World;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminCharacterEventsPaginationTest extends WebTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testEventHistoryIsPaginatedByQueryParams(): void
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

        $entityManager->persist($admin);
        $entityManager->persist($world);
        $entityManager->persist($character);

        for ($i = 0; $i < 55; $i++) {
            $entityManager->persist(new CharacterEvent($world, $character, sprintf('evt.%02d', $i), $i));
        }

        $entityManager->flush();

        $client->loginUser($admin, 'main');
        $crawler = $client->request('GET', sprintf('/admin/characters/%d?perPage=20&page=2', (int)$character->getId()));

        self::assertResponseIsSuccessful();
        self::assertSame('evt.34', trim($crawler->filter('table tbody tr:first-child td:nth-child(3) code')->text()));
    }
}

