<?php

namespace App\Tests\Game\Domain\Stats\Growth;

use App\Game\Domain\Stats\CoreAttributes;
use App\Game\Domain\Stats\Growth\TrainingGrowthService;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use PHPUnit\Framework\TestCase;

final class TrainingGrowthServiceTest extends TestCase
{
    public function testTrainingIncreasesRelevantStats(): void
    {
        $service = new TrainingGrowthService();
        $before  = CoreAttributes::baseline();

        $after = $service->train($before, TrainingIntensity::Normal);

        self::assertGreaterThan($before->strength, $after->strength);
        self::assertGreaterThan($before->kiControl, $after->kiControl);
    }
}

