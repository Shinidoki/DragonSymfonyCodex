<?php

namespace App\Tests\Game\Integration;

use App\Entity\LocalActor;
use App\Entity\LocalSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalActorEntityTest extends KernelTestCase
{
    public function testLocalActorIsInstantiable(): void
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

        $actor = new LocalActor($session, characterId: 1, role: 'player', x: 4, y: 4);

        self::assertSame('player', $actor->getRole());
    }
}

