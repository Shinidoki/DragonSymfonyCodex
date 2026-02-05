<?php

namespace App\Tests\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminSecurityTest extends WebTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testAdminRequiresLogin(): void
    {
        $client = self::createClient();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $client->request('GET', '/admin');
        self::assertResponseRedirects('/login');
    }

    public function testAdminRequiresRoleAdmin(): void
    {
        $client = self::createClient();

        $container     = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get('security.user_password_hasher');

        $user = (new User())->setUsername('user');
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));

        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user, 'main');
        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminWorldsLoadsForAdmin(): void
    {
        $client = self::createClient();

        $container     = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get('security.user_password_hasher');

        $admin = (new User())
            ->setUsername('admin')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, 'password'));

        $entityManager->persist($admin);
        $entityManager->flush();

        $client->loginUser($admin, 'main');
        $client->request('GET', '/admin/worlds');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Worlds');
    }
}
