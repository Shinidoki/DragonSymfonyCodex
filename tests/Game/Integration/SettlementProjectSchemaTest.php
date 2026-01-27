<?php

namespace App\Tests\Game\Integration;

use App\Entity\SettlementBuilding;
use App\Entity\SettlementProject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SettlementProjectSchemaTest extends KernelTestCase
{
    public function testEntitiesAreDiscoverable(): void
    {
        self::bootKernel();

        self::assertTrue(class_exists(SettlementBuilding::class));
        self::assertTrue(class_exists(SettlementProject::class));
    }
}
