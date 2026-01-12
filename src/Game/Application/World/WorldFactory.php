<?php

namespace App\Game\Application\World;

use App\Entity\World;

final class WorldFactory
{
    public function create(string $seed): World
    {
        $seed = trim($seed);
        if ($seed === '') {
            throw new \InvalidArgumentException('Seed must not be empty.');
        }

        return new World($seed);
    }
}

