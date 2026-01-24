<?php

namespace App\Tests\Game\Domain\Goal;

use App\Game\Domain\Goal\GoalStepResult;
use PHPUnit\Framework\TestCase;

final class GoalStepResultTest extends TestCase
{
    public function testExposesEventsList(): void
    {
        self::assertTrue(property_exists(GoalStepResult::class, 'events'));
    }
}

