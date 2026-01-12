<?php

namespace App\Tests\Game\Integration;

use App\Entity\LocalSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalSessionEntityTest extends KernelTestCase
{
    public function testLocalSessionIsInstantiable(): void
    {
        self::bootKernel();

        $session = new LocalSession(
            worldId: 1,
            characterId: 1,
            tileX: 0,
            tileY: 0,
            width: 8,
            height: 8,
            playerX: 4,
            playerY: 4,
        );

        self::assertSame(8, $session->getWidth());
        self::assertSame('active', $session->getStatus());
    }
}

