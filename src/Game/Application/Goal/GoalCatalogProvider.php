<?php

namespace App\Game\Application\Goal;

use App\Game\Domain\Goal\GoalCatalog;
use App\Game\Domain\Goal\GoalCatalogLoader;
use Symfony\Component\HttpKernel\KernelInterface;

final class GoalCatalogProvider implements GoalCatalogProviderInterface
{
    private ?GoalCatalog $catalog = null;

    public function __construct(
        private readonly GoalCatalogLoader $loader,
        private readonly KernelInterface   $kernel,
    )
    {
    }

    public function get(): GoalCatalog
    {
        if ($this->catalog instanceof GoalCatalog) {
            return $this->catalog;
        }

        $path          = $this->kernel->getProjectDir() . '/config/game/goals.yaml';
        $this->catalog = $this->loader->loadFromFile($path);

        return $this->catalog;
    }
}

