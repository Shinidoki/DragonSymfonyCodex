<?php

declare(strict_types=1);

namespace App\Game\Application\Simulation;

use App\Game\Domain\Simulation\Balancing\SimulationBalancingCatalog;

interface SimulationBalancingCatalogProviderInterface
{
    public function get(): SimulationBalancingCatalog;
}
