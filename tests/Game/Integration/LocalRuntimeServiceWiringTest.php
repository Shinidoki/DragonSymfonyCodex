<?php

declare(strict_types=1);

namespace App\Tests\Game\Integration;

use App\Game\Application\Local\ApplyLocalActionHandler;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Application\Local\ExitLocalModeHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalRuntimeServiceWiringTest extends KernelTestCase
{
    public function testContainerDoesNotWireLocalRuntimeHandlers(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        self::assertFalse($container->has(EnterLocalModeHandler::class));
        self::assertFalse($container->has(ApplyLocalActionHandler::class));
        self::assertFalse($container->has(ExitLocalModeHandler::class));
    }
}
