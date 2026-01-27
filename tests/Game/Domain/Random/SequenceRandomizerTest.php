<?php

namespace App\Tests\Game\Domain\Random;

use App\Game\Domain\Random\SequenceRandomizer;
use PHPUnit\Framework\TestCase;

final class SequenceRandomizerTest extends TestCase
{
    public function testNextIntClampsAndRepeatsLastValue(): void
    {
        $rng = new SequenceRandomizer([5, 999]);

        self::assertSame(5, $rng->nextInt(1, 10));
        self::assertSame(10, $rng->nextInt(1, 10));
        self::assertSame(10, $rng->nextInt(1, 10));
    }

    public function testChanceUsesDeterministicIntStream(): void
    {
        // p=0.5 => threshold=500_000
        $rng = new SequenceRandomizer([500_000, 500_001]);

        self::assertTrue($rng->chance(0.5));
        self::assertFalse($rng->chance(0.5));
    }
}
