<?php

namespace App\Tests\Game\Domain\LocalTurns;

use App\Game\Domain\LocalTurns\TurnScheduler;
use PHPUnit\Framework\TestCase;

final class TurnSchedulerTest extends TestCase
{
    public function testFasterActorGetsMoreTurnsDeterministically(): void
    {
        $scheduler = new TurnScheduler(threshold: 100);

        $actors = [
            ['id' => 1, 'speed' => 10, 'meter' => 0],
            ['id' => 2, 'speed' => 30, 'meter' => 0],
        ];

        $counts = [1 => 0, 2 => 0];
        for ($i = 0; $i < 60; $i++) {
            $next = $scheduler->pickNextActorId($actors);
            $counts[$next]++;
        }

        self::assertGreaterThan($counts[1], $counts[2]);
    }

    public function testTieBreaksByLowestActorId(): void
    {
        $scheduler = new TurnScheduler(threshold: 100);

        $actors = [
            ['id' => 1, 'speed' => 10, 'meter' => 0],
            ['id' => 2, 'speed' => 10, 'meter' => 0],
        ];

        $first = $scheduler->pickNextActorId($actors);

        self::assertSame(1, $first);
    }
}