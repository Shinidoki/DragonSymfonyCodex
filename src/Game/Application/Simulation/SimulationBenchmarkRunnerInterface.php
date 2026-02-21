<?php

declare(strict_types=1);

namespace App\Game\Application\Simulation;

use App\Entity\World;

interface SimulationBenchmarkRunnerInterface
{
    /** @return array{passed:bool,profile:string,sample_size?:int,violations:list<array<string,mixed>>} */
    public function run(World $world, int $days, string $profile): array;
}
