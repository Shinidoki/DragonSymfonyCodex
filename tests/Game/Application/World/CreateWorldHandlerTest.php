<?php

namespace App\Tests\Game\Application\World;

use App\Entity\World;
use App\Game\Application\World\CreateWorldHandler;
use App\Game\Application\World\WorldFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CreateWorldHandlerTest extends TestCase
{
    public function testCreatePersistsWorldAndReturnsIt(): void
    {
        $factory = new WorldFactory();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(World::class));
        $entityManager->expects(self::once())
            ->method('flush');

        $handler = new CreateWorldHandler($factory, $entityManager);

        $world = $handler->create('seed-1');

        self::assertSame('seed-1', $world->getSeed());
        self::assertSame(0, $world->getCurrentDay());
    }
}

