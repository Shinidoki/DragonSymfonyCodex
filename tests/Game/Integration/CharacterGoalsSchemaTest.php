<?php

namespace App\Tests\Game\Integration;

use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CharacterGoalsSchemaTest extends KernelTestCase
{
    public function testEntitiesAreDiscoverable(): void
    {
        self::bootKernel();

        self::assertTrue(class_exists(CharacterGoal::class));
        self::assertTrue(class_exists(CharacterEvent::class));
    }
}

