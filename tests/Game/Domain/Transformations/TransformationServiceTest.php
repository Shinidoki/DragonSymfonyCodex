<?php

namespace App\Tests\Game\Domain\Transformations;

use App\Game\Domain\Stats\CoreAttributes;
use App\Game\Domain\Transformations\Transformation;
use App\Game\Domain\Transformations\TransformationService;
use App\Game\Domain\Transformations\TransformationState;
use PHPUnit\Framework\TestCase;

final class TransformationServiceTest extends TestCase
{
    public function testActivationAppliesMultiplierAndOveruseCausesExhaustion(): void
    {
        $service = new TransformationService();
        $base    = new CoreAttributes(
            strength: 10,
            speed: 10,
            endurance: 10,
            durability: 10,
            kiCapacity: 10,
            kiControl: 10,
            kiRecovery: 10,
            focus: 10,
            discipline: 10,
            adaptability: 10,
        );

        $state = $service->activate(TransformationState::none(), Transformation::SuperSaiyan);

        $effective = $service->effectiveAttributes($base, $state);
        self::assertSame(20, $effective->strength);

        $state = $state->tick()->tick()->tick()->tick();
        $state = $service->deactivate($state);

        self::assertGreaterThan(0, $state->exhaustionDaysRemaining);

        $exhaustedEffective = $service->effectiveAttributes($base, $state);
        self::assertLessThan($base->strength, $exhaustedEffective->strength);
    }
}

