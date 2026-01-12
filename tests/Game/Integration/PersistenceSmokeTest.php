<?php

namespace App\Tests\Game\Integration;

use App\Entity\World;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PersistenceSmokeTest extends KernelTestCase
{
    public function testWorldEntityBootsAndIsMappable(): void
    {
        self::bootKernel();

        self::assertSame('seed-1', (new World('seed-1'))->getSeed());
    }
}

