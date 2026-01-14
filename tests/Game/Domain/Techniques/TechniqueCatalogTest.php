<?php

namespace App\Tests\Game\Domain\Techniques;

use App\Game\Domain\Techniques\Technique;
use App\Game\Domain\Techniques\TechniqueCatalog;
use PHPUnit\Framework\TestCase;

final class TechniqueCatalogTest extends TestCase
{
    public function testKiBlastHasCostAndRange(): void
    {
        $c = new TechniqueCatalog();

        self::assertSame(3, $c->kiCost(Technique::KiBlast));
        self::assertSame(2, $c->range(Technique::KiBlast));
    }
}

