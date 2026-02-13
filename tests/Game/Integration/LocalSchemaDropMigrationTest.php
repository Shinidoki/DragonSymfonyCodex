<?php

declare(strict_types=1);

namespace App\Tests\Game\Integration;

use PHPUnit\Framework\TestCase;

final class LocalSchemaDropMigrationTest extends TestCase
{
    public function testLatestCutoverMigrationDropsLocalZoneTables(): void
    {
        $path = dirname(__DIR__, 3) . '/migrations/Version20260213093000.php';
        self::assertFileExists($path);

        $contents = (string) file_get_contents($path);

        self::assertStringContainsString('DROP TABLE IF EXISTS local_intent', $contents);
        self::assertStringContainsString('DROP TABLE IF EXISTS local_event', $contents);
        self::assertStringContainsString('DROP TABLE IF EXISTS local_combatant', $contents);
        self::assertStringContainsString('DROP TABLE IF EXISTS local_combat', $contents);
        self::assertStringContainsString('DROP TABLE IF EXISTS local_actor', $contents);
        self::assertStringContainsString('DROP TABLE IF EXISTS local_session', $contents);
    }
}
