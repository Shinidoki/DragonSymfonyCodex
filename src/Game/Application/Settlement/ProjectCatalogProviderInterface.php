<?php

namespace App\Game\Application\Settlement;

use App\Game\Domain\Settlement\ProjectCatalog;

interface ProjectCatalogProviderInterface
{
    public function get(): ProjectCatalog;
}
