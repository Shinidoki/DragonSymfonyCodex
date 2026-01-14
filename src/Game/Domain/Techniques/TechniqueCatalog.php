<?php

namespace App\Game\Domain\Techniques;

final class TechniqueCatalog
{
    public function kiCost(Technique $technique): int
    {
        return match ($technique) {
            Technique::KiBlast => 3,
        };
    }

    public function range(Technique $technique): int
    {
        return match ($technique) {
            Technique::KiBlast => 2,
        };
    }
}

