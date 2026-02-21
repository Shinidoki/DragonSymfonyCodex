<?php

declare(strict_types=1);

namespace App\Game\Application\Simulation;

use App\Game\Domain\Simulation\Balancing\SimulationBalancingCatalog;
use App\Game\Domain\Simulation\Balancing\SimulationBalancingCatalogLoader;
use Symfony\Component\HttpKernel\KernelInterface;

final class YamlSimulationBalancingCatalogProvider implements SimulationBalancingCatalogProviderInterface
{
    private ?SimulationBalancingCatalog $catalog = null;

    public function __construct(
        private readonly SimulationBalancingCatalogLoader $loader,
        private readonly KernelInterface $kernel,
    ) {
    }

    public function get(): SimulationBalancingCatalog
    {
        if ($this->catalog instanceof SimulationBalancingCatalog) {
            return $this->catalog;
        }

        $path = $this->kernel->getProjectDir() . '/config/game/simulation_balancing.yaml';
        $this->catalog = $this->loader->loadFromFile($path);

        return $this->catalog;
    }
}
