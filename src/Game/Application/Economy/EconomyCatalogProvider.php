<?php

namespace App\Game\Application\Economy;

use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Economy\EconomyCatalogLoader;
use Symfony\Component\HttpKernel\KernelInterface;

final class EconomyCatalogProvider implements EconomyCatalogProviderInterface
{
    private ?EconomyCatalog $catalog = null;

    public function __construct(
        private readonly EconomyCatalogLoader $loader,
        private readonly KernelInterface      $kernel,
    )
    {
    }

    public function get(): EconomyCatalog
    {
        if ($this->catalog instanceof EconomyCatalog) {
            return $this->catalog;
        }

        $path          = $this->kernel->getProjectDir() . '/config/game/economy.yaml';
        $this->catalog = $this->loader->loadFromFile($path);

        return $this->catalog;
    }
}

