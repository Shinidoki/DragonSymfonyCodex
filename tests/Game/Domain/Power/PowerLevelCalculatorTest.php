<?php

namespace App\Tests\Game\Domain\Power;

use App\Game\Domain\Power\PowerLevelCalculator;
use App\Game\Domain\Stats\CoreAttributes;
use PHPUnit\Framework\TestCase;

final class PowerLevelCalculatorTest extends TestCase
{
    public function testComputesStablePowerLevelForBaselineAttributes(): void
    {
        $calculator = new PowerLevelCalculator();

        self::assertSame(39, $calculator->calculate(CoreAttributes::baseline()));
    }

    public function testPowerIncreasesWhenAttributesIncrease(): void
    {
        $calculator = new PowerLevelCalculator();
        $baseline   = CoreAttributes::baseline();

        $stronger = new CoreAttributes(
            strength: 10,
            speed: $baseline->speed,
            endurance: $baseline->endurance,
            durability: $baseline->durability,
            kiCapacity: $baseline->kiCapacity,
            kiControl: $baseline->kiControl,
            kiRecovery: $baseline->kiRecovery,
            focus: $baseline->focus,
            discipline: $baseline->discipline,
            adaptability: $baseline->adaptability,
        );

        self::assertGreaterThan($calculator->calculate($baseline), $calculator->calculate($stronger));
    }
}

