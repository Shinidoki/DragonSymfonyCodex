<?php

namespace App\Game\Application\Settlement;

use App\Game\Domain\Settlement\ProjectCatalog;
use App\Game\Domain\Settlement\ProjectCatalogLoader;
use Symfony\Component\HttpKernel\KernelInterface;

final class YamlProjectCatalogProvider implements ProjectCatalogProviderInterface
{
    private ?ProjectCatalog $catalog = null;

    public function __construct(
        private readonly ProjectCatalogLoader $loader,
        private readonly KernelInterface      $kernel,
    )
    {
    }

    public function get(): ProjectCatalog
    {
        if ($this->catalog instanceof ProjectCatalog) {
            return $this->catalog;
        }

        $path          = $this->kernel->getProjectDir() . '/config/game/projects.yaml';
        $this->catalog = $this->loader->loadFromFile($path);

        return $this->catalog;
    }
}
