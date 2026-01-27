<?php

namespace App\Game\Application\Simulation;

use App\Entity\Settlement;
use App\Game\Application\Settlement\ProjectCatalogProviderInterface;
use App\Game\Domain\Map\TileCoord;
use App\Repository\SettlementBuildingRepository;
use App\Repository\SettlementProjectRepository;

final class SettlementSimulationContextBuilder
{
    public function __construct(
        private readonly ?SettlementProjectRepository     $settlementProjects = null,
        private readonly ?SettlementBuildingRepository    $settlementBuildings = null,
        private readonly ?ProjectCatalogProviderInterface $projectCatalogProvider = null,
    )
    {
    }

    /**
     * @param list<TileCoord>  $dojoCoords
     * @param list<Settlement> $settlements
     *
     * @return array{
     *   0:array<string,array<string,int>>,
     *   1:array<string,array{building_code:string,target_level:int}>,
     *   2:array<string,float>,
     *   3:array<string,int>,
     *   4:array<string,int>
     * }
     */
    public function build(array $dojoCoords, array $settlements): array
    {
        if (!$this->settlementProjects instanceof SettlementProjectRepository || !$this->settlementBuildings instanceof SettlementBuildingRepository) {
            return [[], [], [], [], []];
        }
        if ($settlements === []) {
            return [[], [], [], [], []];
        }

        $dojoIndex = [];
        foreach ($dojoCoords as $coord) {
            $dojoIndex[sprintf('%d:%d', $coord->x, $coord->y)] = true;
        }

        $buildingsByCoord               = [];
        $projectsByCoord                = [];
        $dojoTrainingMultipliersByCoord = [];
        $dojoMasterCharacterIdByCoord   = [];
        $dojoTrainingFeesByCoord        = [];
        $catalog                        = $this->projectCatalogProvider instanceof ProjectCatalogProviderInterface
            ? $this->projectCatalogProvider->get()
            : null;

        foreach ($settlements as $settlement) {
            $key = sprintf('%d:%d', $settlement->getX(), $settlement->getY());

            $dojo = $this->settlementBuildings->findOneBySettlementAndCode($settlement, 'dojo');
            if ($dojo !== null) {
                $buildingsByCoord[$key]['dojo'] = $dojo->getLevel();
                $masterId                       = $dojo->getMasterCharacter()?->getId();
                if (is_int($masterId) && $masterId > 0) {
                    $dojoMasterCharacterIdByCoord[$key] = $masterId;
                }

                if (
                    $catalog !== null
                    && $dojo->getLevel() > 0
                    && is_int($masterId)
                    && $masterId > 0
                ) {
                    $dojoTrainingMultipliersByCoord[$key] = $catalog->dojoTrainingMultiplier($dojo->getLevel());
                    $dojoTrainingFeesByCoord[$key]        = $catalog->dojoTrainingFee($dojo->getLevel());
                }
            } elseif (isset($dojoIndex[$key])) {
                $buildingsByCoord[$key]['dojo'] = 1;
            }

            $active = $this->settlementProjects->findActiveForSettlement($settlement);
            if ($active !== null) {
                $projectsByCoord[$key] = [
                    'building_code' => $active->getBuildingCode(),
                    'target_level'  => $active->getTargetLevel(),
                ];
            }
        }

        return [
            $buildingsByCoord,
            $projectsByCoord,
            $dojoTrainingMultipliersByCoord,
            $dojoMasterCharacterIdByCoord,
            $dojoTrainingFeesByCoord,
        ];
    }
}
