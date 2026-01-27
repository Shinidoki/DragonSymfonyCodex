<?php

namespace App\Game\Application\Settlement;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\SettlementBuilding;
use App\Entity\SettlementProject;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Repository\SettlementBuildingRepository;
use App\Repository\SettlementProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SettlementProjectLifecycleService
{
    public function __construct(
        private readonly EntityManagerInterface          $entityManager,
        private readonly SettlementProjectRepository     $projects,
        private readonly SettlementBuildingRepository    $buildings,
        private readonly ProjectCatalogProviderInterface $catalogProvider,
    )
    {
    }

    /**
     * @param list<Settlement>     $settlements
     * @param list<CharacterEvent> $emittedEvents
     * @param list<Character>      $characters
     *
     * @return list<CharacterEvent>
     */
    public function advanceDay(
        World           $world,
        int             $worldDay,
        array           $settlements,
        array           $emittedEvents,
        array           $characters = [],
        ?EconomyCatalog $economyCatalog = null,
    ): array
    {
        if ($worldDay < 0) {
            throw new \InvalidArgumentException('worldDay must be >= 0.');
        }

        if ($settlements === []) {
            return [];
        }

        $catalog = $this->catalogProvider->get();

        $settlementsByCoord = [];
        foreach ($settlements as $s) {
            $settlementsByCoord[sprintf('%d:%d', $s->getX(), $s->getY())] = $s;
        }

        $this->materializeRequestedProjects($worldDay, $settlementsByCoord, $emittedEvents, $catalog);

        $events = [];
        if ($economyCatalog instanceof EconomyCatalog && $characters !== []) {
            $events = $this->advanceActiveProjects(
                world: $world,
                worldDay: $worldDay,
                settlements: $settlements,
                settlementsByCoord: $settlementsByCoord,
                characters: $characters,
                economyCatalog: $economyCatalog,
                catalog: $catalog,
            );
        }

        $this->entityManager->flush();

        return $events;
    }

    /**
     * @param array<string,Settlement> $settlementsByCoord
     * @param list<CharacterEvent>     $emittedEvents
     */
    private function materializeRequestedProjects(int $worldDay, array $settlementsByCoord, array $emittedEvents, \App\Game\Domain\Settlement\ProjectCatalog $catalog): void
    {
        if ($emittedEvents === []) {
            return;
        }

        foreach ($emittedEvents as $event) {
            if ($event->getType() !== 'settlement_project_start_requested') {
                continue;
            }

            $eventId = $event->getId();
            if ($eventId === null) {
                continue;
            }

            $existing = $this->projects->findOneBy(['requestEventId' => $eventId]);
            if ($existing instanceof SettlementProject) {
                continue;
            }

            $data = $event->getData();
            if (!is_array($data)) {
                continue;
            }

            $x = $data['settlement_x'] ?? null;
            $y = $data['settlement_y'] ?? null;
            if (!is_int($x) || !is_int($y) || $x < 0 || $y < 0) {
                continue;
            }

            $settlement = $settlementsByCoord[sprintf('%d:%d', $x, $y)] ?? null;
            if (!$settlement instanceof Settlement) {
                continue;
            }

            if ($this->projects->findActiveForSettlement($settlement) instanceof SettlementProject) {
                continue;
            }

            $buildingCode = $data['building_code'] ?? null;
            if (!is_string($buildingCode) || trim($buildingCode) === '') {
                continue;
            }
            $buildingCode = strtolower(trim($buildingCode));

            $targetLevel = $data['target_level'] ?? null;
            if (!is_int($targetLevel) || $targetLevel <= 0) {
                continue;
            }

            if ($buildingCode !== 'dojo') {
                continue;
            }

            $currentBuilding = $this->buildings->findOneBySettlementAndCode($settlement, $buildingCode);
            $currentLevel    = $currentBuilding?->getLevel() ?? 0;

            $expected = $catalog->dojoNextLevel($currentLevel);
            if ($expected === null || $expected !== $targetLevel) {
                continue;
            }

            $dojoDefs = $catalog->dojoLevelDefs();
            $def      = $dojoDefs[$targetLevel] ?? null;
            if (!is_array($def)) {
                continue;
            }

            $materialsCost = $def['materials_cost'] ?? null;
            $requiredBase  = $def['base_required_work_units'] ?? null;

            if (!is_int($materialsCost) || $materialsCost < 0) {
                continue;
            }
            if (!is_int($requiredBase) || $requiredBase <= 0) {
                continue;
            }

            if ($settlement->getTreasury() < $materialsCost) {
                continue;
            }

            $settlement->addToTreasury(-$materialsCost);

            $project = new SettlementProject(
                settlement: $settlement,
                buildingCode: $buildingCode,
                targetLevel: $targetLevel,
                requiredWorkUnits: $requiredBase,
                startedDay: $worldDay,
                requestEventId: $eventId,
            );
            $this->entityManager->persist($project);
        }
    }

    /**
     * @param list<Settlement>         $settlements
     * @param array<string,Settlement> $settlementsByCoord
     * @param list<Character>          $characters
     *
     * @return list<CharacterEvent>
     */
    private function advanceActiveProjects(
        World                                      $world,
        int                                        $worldDay,
        array                                      $settlements,
        array                                      $settlementsByCoord,
        array                                      $characters,
        EconomyCatalog                             $economyCatalog,
        \App\Game\Domain\Settlement\ProjectCatalog $catalog,
    ): array
    {
        $events = [];

        foreach ($settlements as $settlement) {
            $project = $this->projects->findActiveForSettlement($settlement);
            if (!$project instanceof SettlementProject) {
                continue;
            }
            if ($project->getLastSimDayApplied() === $worldDay) {
                continue;
            }
            if ($project->getStartedDay() === $worldDay) {
                $project->setLastSimDayApplied($worldDay);
                continue;
            }

            $def = $this->projectDef($catalog, $project->getBuildingCode(), $project->getTargetLevel());
            if ($def === null) {
                continue;
            }

            $diversion = $def['diversion_fraction'] ?? null;
            if (!is_float($diversion) && !is_int($diversion)) {
                $diversion = 0.0;
            }
            $diversion = (float)$diversion;
            if ($diversion < 0.0) {
                $diversion = 0.0;
            }
            if ($diversion > 1.0) {
                $diversion = 1.0;
            }

            $availableWorkUnits = $this->availableSettlementWorkUnits($settlement, $characters, $economyCatalog);

            $invested = (int)floor($availableWorkUnits * $diversion);
            if ($invested <= 0 && $availableWorkUnits > 0.0 && $diversion > 0.0) {
                $invested = 1;
            }

            if ($invested > 0) {
                $perWorkUnit = $economyCatalog->settlementPerWorkUnitBase()
                    + ($settlement->getProsperity() * $economyCatalog->settlementPerWorkUnitProsperityMult());
                if ($perWorkUnit < 0) {
                    $perWorkUnit = 0;
                }

                $cost = (int)floor($perWorkUnit * $invested);
                if ($cost > 0) {
                    $settlement->addToTreasury(-$cost);
                }

                $project->addProgressWorkUnits($invested);
            }

            $project->setLastSimDayApplied($worldDay);

            if ($project->getProgressWorkUnits() >= $project->getRequiredWorkUnits()) {
                $project->markCompleted();

                $building = $this->buildings->findOneBySettlementAndCode($settlement, $project->getBuildingCode());
                if (!$building instanceof SettlementBuilding) {
                    $building = new SettlementBuilding($settlement, $project->getBuildingCode(), $project->getTargetLevel());
                    $this->entityManager->persist($building);
                } else {
                    $building->setLevel(max($building->getLevel(), $project->getTargetLevel()));
                }

                if ($project->getBuildingCode() === 'dojo' && $project->getTargetLevel() >= 1) {
                    $tile = $this->entityManager->getRepository(WorldMapTile::class)->findOneBy([
                        'world' => $world,
                        'x'     => $settlement->getX(),
                        'y'     => $settlement->getY(),
                    ]);
                    if ($tile instanceof WorldMapTile && !$tile->hasDojo()) {
                        $tile->setHasDojo(true);
                    }
                }

                $events[] = new CharacterEvent(
                    world: $world,
                    character: null,
                    type: 'settlement_project_completed',
                    day: $worldDay,
                    data: [
                        'settlement_x'  => $settlement->getX(),
                        'settlement_y'  => $settlement->getY(),
                        'building_code' => $project->getBuildingCode(),
                        'level'         => $project->getTargetLevel(),
                    ],
                );
            }
        }

        return $events;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function projectDef(\App\Game\Domain\Settlement\ProjectCatalog $catalog, string $buildingCode, int $targetLevel): ?array
    {
        if ($buildingCode !== 'dojo') {
            return null;
        }

        $defs = $catalog->dojoLevelDefs();

        return $defs[$targetLevel] ?? null;
    }

    /**
     * @param list<Character> $characters
     */
    private function availableSettlementWorkUnits(Settlement $settlement, array $characters, EconomyCatalog $economyCatalog): float
    {
        $sx = $settlement->getX();
        $sy = $settlement->getY();

        $sum = 0.0;
        foreach ($characters as $character) {
            if (!$character->isEmployed()) {
                continue;
            }
            if ((int)$character->getEmploymentSettlementX() !== $sx || (int)$character->getEmploymentSettlementY() !== $sy) {
                continue;
            }

            $jobCode = $character->getEmploymentJobCode();
            if (!is_string($jobCode) || trim($jobCode) === '') {
                continue;
            }

            $radius = $economyCatalog->jobWorkRadius($jobCode);
            $dist   = abs($character->getTileX() - $sx) + abs($character->getTileY() - $sy);
            if ($dist > $radius) {
                continue;
            }

            $workFraction = max(0.0, min(1.0, $character->getWorkFocus() / 100));
            if ($workFraction <= 0.0) {
                continue;
            }

            $sum += $economyCatalog->jobWageWeight($jobCode) * $workFraction;
        }

        return $sum;
    }
}
