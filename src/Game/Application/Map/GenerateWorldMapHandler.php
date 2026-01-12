<?php

namespace App\Game\Application\Map;

use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Domain\Map\MapGenerator;
use App\Game\Domain\Map\TileCoord;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class GenerateWorldMapHandler
{
    public function __construct(
        private readonly MapGenerator           $mapGenerator,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    /**
     * @return array{created:int, updated:int, total:int}
     */
    public function generate(int $worldId, int $width, int $height, string $planetName = 'Earth'): array
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Width and height must be positive.');
        }

        $world = $this->entityManager->find(World::class, $worldId);
        if (!$world instanceof World) {
            throw new \RuntimeException(sprintf('World not found: %d', $worldId));
        }

        $world->setPlanetName($planetName);
        $world->setMapSize($width, $height);

        /** @var EntityRepository<WorldMapTile> $tileRepository */
        $tileRepository = $this->entityManager->getRepository(WorldMapTile::class);

        $created = 0;
        $updated = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $biome = $this->mapGenerator->biomeFor($world->getSeed(), new TileCoord($x, $y));

                $tile = $tileRepository->findOneBy([
                    'world' => $world,
                    'x'     => $x,
                    'y'     => $y,
                ]);

                if (!$tile instanceof WorldMapTile) {
                    $tile = new WorldMapTile($world, $x, $y, $biome);
                    $this->entityManager->persist($tile);
                    $created++;
                    continue;
                }

                if ($tile->getBiome() !== $biome) {
                    $tile->setBiome($biome);
                    $updated++;
                }
            }
        }

        $this->entityManager->flush();

        return [
            'created' => $created,
            'updated' => $updated,
            'total'   => $width * $height,
        ];
    }
}
