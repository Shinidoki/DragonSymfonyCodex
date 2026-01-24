<?php

namespace App\Tests\Game\Domain\Goal;

use App\Game\Domain\Goal\GoalCatalogLoader;
use PHPUnit\Framework\TestCase;

final class GoalCatalogLoaderTest extends TestCase
{
    public function testLoadsYamlAndExposesLifeGoalsAndCurrentGoals(): void
    {
        $loader  = new GoalCatalogLoader();
        $catalog = $loader->loadFromFile(__DIR__ . '/fixtures/goals.yaml');

        self::assertNotEmpty($catalog->lifeGoals());
        self::assertNotEmpty($catalog->currentGoals());
    }
}

