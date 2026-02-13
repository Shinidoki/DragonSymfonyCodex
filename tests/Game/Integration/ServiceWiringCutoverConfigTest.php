<?php

declare(strict_types=1);

namespace App\Tests\Game\Integration;

use PHPUnit\Framework\TestCase;

final class ServiceWiringCutoverConfigTest extends TestCase
{
    public function testServicesConfigExcludesLocalRuntimeNamespaces(): void
    {
        $contents = (string) file_get_contents(__DIR__ . '/../../../config/services.yaml');

        self::assertStringContainsString("- '../src/Game/Application/Local/'", $contents);
        self::assertStringContainsString("- '../src/Game/Domain/LocalMap/'", $contents);
        self::assertStringContainsString("- '../src/Game/Domain/LocalNpc/'", $contents);
        self::assertStringContainsString("- '../src/Game/Domain/LocalTurns/'", $contents);
    }
}
