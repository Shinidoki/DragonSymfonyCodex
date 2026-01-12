<?php

namespace App\Tests\Game\Domain\Stats;

use App\Game\Domain\Stats\StatTier;
use PHPUnit\Framework\TestCase;

final class StatTierTest extends TestCase
{
    public function testMapsNumericStatToTierLabel(): void
    {
        self::assertSame('Weak', StatTier::fromValue(1)->label());
        self::assertSame('Weak', StatTier::fromValue(4)->label());
        self::assertSame('Average', StatTier::fromValue(5)->label());
        self::assertSame('Average', StatTier::fromValue(14)->label());
        self::assertSame('Trained', StatTier::fromValue(15)->label());
    }
}

