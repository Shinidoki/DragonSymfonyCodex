<?php

namespace App\Game\Application\Local;

use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Domain\Training\TrainingContext;
use Doctrine\ORM\EntityManagerInterface;

final class TrainingContextResolver
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function forWorldTile(int $worldId, int $tileX, int $tileY): TrainingContext
    {
        if ($worldId <= 0) {
            throw new \InvalidArgumentException('worldId must be positive.');
        }
        if ($tileX < 0 || $tileY < 0) {
            throw new \InvalidArgumentException('tile coordinates must be >= 0.');
        }

        $world = $this->entityManager->find(World::class, $worldId);
        if (!$world instanceof World) {
            throw new \RuntimeException(sprintf('World not found: %d', $worldId));
        }

        $tile = $this->entityManager->getRepository(WorldMapTile::class)->findOneBy([
            'world' => $world,
            'x'     => $tileX,
            'y'     => $tileY,
        ]);

        if (!$tile instanceof WorldMapTile) {
            return TrainingContext::Wilderness;
        }

        return $tile->hasDojo() ? TrainingContext::Dojo : TrainingContext::Wilderness;
    }
}

