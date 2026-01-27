<?php

namespace App\Tests\Game\Domain\Settlement;

use App\Game\Domain\Settlement\ProjectCatalogLoader;
use PHPUnit\Framework\TestCase;

final class ProjectCatalogLoaderTest extends TestCase
{
    public function testLoadsYamlAndExposesDojoLevelData(): void
    {
        $loader  = new ProjectCatalogLoader();
        $catalog = $loader->loadFromFile(__DIR__ . '/fixtures/projects.yaml');

        self::assertSame(1.2, $catalog->dojoTrainingMultiplier(1));
        self::assertSame(1.35, $catalog->dojoTrainingMultiplier(2));
        self::assertSame(1.5, $catalog->dojoTrainingMultiplier(3));

        self::assertSame(10, $catalog->dojoTrainingFee(1));
        self::assertSame(15, $catalog->dojoTrainingFee(2));
        self::assertSame(7, $catalog->dojoChallengeCooldownDays());

        self::assertSame(1, $catalog->dojoNextLevel(0));
        self::assertSame(2, $catalog->dojoNextLevel(1));
        self::assertSame(3, $catalog->dojoNextLevel(2));
        self::assertNull($catalog->dojoNextLevel(3));

        $defs = $catalog->dojoLevelDefs();
        self::assertArrayHasKey(1, $defs);
        self::assertArrayHasKey(2, $defs);
        self::assertArrayHasKey(3, $defs);
    }

    public function testRejectsMissingDojoLevelDefinitions(): void
    {
        $loader = new ProjectCatalogLoader();

        $this->expectException(\InvalidArgumentException::class);
        $loader->loadFromFile(__DIR__ . '/fixtures/projects_invalid_missing_dojo.yaml');
    }
}
