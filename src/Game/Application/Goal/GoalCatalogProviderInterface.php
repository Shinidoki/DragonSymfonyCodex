<?php

namespace App\Game\Application\Goal;

use App\Game\Domain\Goal\GoalCatalog;

interface GoalCatalogProviderInterface
{
    public function get(): GoalCatalog;
}

