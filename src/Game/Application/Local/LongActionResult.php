<?php

namespace App\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalSession;
use App\Entity\World;

final readonly class LongActionResult
{
    public function __construct(
        public World        $world,
        public Character    $character,
        public LocalSession $session,
        public int          $daysAdvanced,
    )
    {
    }
}

