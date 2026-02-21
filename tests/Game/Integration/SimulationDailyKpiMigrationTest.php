<?php

declare(strict_types=1);

namespace App\Tests\Game\Integration;

use PHPUnit\Framework\TestCase;

final class SimulationDailyKpiMigrationTest extends TestCase
{
    public function testSimulationDailyKpiMigrationUsesMySqlCompatibleAutoIncrementSyntax(): void
    {
        $path = dirname(__DIR__, 3) . '/migrations/Version20260221115800.php';
        self::assertFileExists($path);

        $contents = (string) file_get_contents($path);

        self::assertStringContainsString('AUTO_INCREMENT', $contents);
        self::assertStringNotContainsString('AUTOINCREMENT', $contents);
    }
}
