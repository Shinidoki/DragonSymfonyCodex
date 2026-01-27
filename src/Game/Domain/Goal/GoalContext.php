<?php

namespace App\Game\Domain\Goal;

use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Map\TileCoord;

final readonly class GoalContext
{
    /**
     * @param list<TileCoord> $dojoTiles
     * @param list<TileCoord> $settlementTiles
     * @param array<string,Settlement> $settlementsByCoord
     * @param array<string,array<string,int>>                            $settlementBuildingsByCoord
     * @param array<string,array{building_code:string,target_level:int}> $activeSettlementProjectsByCoord
     * @param list<CharacterEvent> $events
     */
    public function __construct(
        public array           $dojoTiles = [],
        public array           $settlementTiles = [],
        public array           $settlementsByCoord = [],
        public array $settlementBuildingsByCoord = [],
        public array $activeSettlementProjectsByCoord = [],
        public ?EconomyCatalog $economyCatalog = null,
        public array $events = [],
    )
    {
    }
}
