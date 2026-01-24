<?php

namespace App\Game\Application\Simulation;

use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use App\Repository\CharacterRepository;
use App\Repository\NpcProfileRepository;
use App\Repository\WorldMapTileRepository;
use App\Repository\WorldRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AdvanceDayHandler
{
    public function __construct(
        private readonly WorldRepository        $worldRepository,
        private readonly CharacterRepository    $characterRepository,
        private readonly NpcProfileRepository   $npcProfiles,
        private readonly WorldMapTileRepository $tiles,
        private readonly SimulationClock        $clock,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function advance(int $worldId, int $days): AdvanceDayResult
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Days must be >= 0.');
        }

        $world = $this->worldRepository->find($worldId);
        if (!$world instanceof World) {
            throw new \RuntimeException(sprintf('World not found: %d', $worldId));
        }

        /** @var list<\App\Entity\Character> $characters */
        $characters = $this->characterRepository->findBy(['world' => $world]);

        $profilesByCharacterId = [];
        foreach ($this->npcProfiles->findByWorld($world) as $profile) {
            $id = $profile->getCharacter()->getId();
            if ($id !== null) {
                $profilesByCharacterId[(int)$id] = $profile;
            }
        }

        /** @var list<WorldMapTile> $dojoTiles */
        $dojoTiles  = $this->tiles->findBy(['world' => $world, 'hasDojo' => true]);
        $dojoCoords = array_map(
            static fn(WorldMapTile $tile): TileCoord => new TileCoord($tile->getX(), $tile->getY()),
            $dojoTiles,
        );

        $this->clock->advanceDays($world, $characters, $days, TrainingIntensity::Normal, $profilesByCharacterId, $dojoCoords);
        $this->entityManager->flush();

        return new AdvanceDayResult($world, $characters, $days);
    }
}
