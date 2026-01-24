<?php

namespace App\Game\Application\Economy;

use App\Game\Domain\Economy\EconomyCatalog;

interface EconomyCatalogProviderInterface
{
    public function get(): EconomyCatalog;
}

