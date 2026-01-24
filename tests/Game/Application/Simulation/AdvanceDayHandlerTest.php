<?php

namespace App\Tests\Game\Application\Simulation;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Domain\Race;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingGrowthService;
use App\Repository\CharacterRepository;
use App\Repository\NpcProfileRepository;
use App\Repository\WorldMapTileRepository;
use App\Repository\WorldRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AdvanceDayHandlerTest extends TestCase
{
    public function testAdvanceIncrementsWorldDayAndTrainsCharacters(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'Gohan', Race::Human);

        $worldRepository = $this->createMock(WorldRepository::class);
        $worldRepository->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($world);

        $characterRepository = $this->createMock(CharacterRepository::class);
        $characterRepository->expects(self::once())
            ->method('findBy')
            ->with(['world' => $world])
            ->willReturn([$character]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $clock = new SimulationClock(new TrainingGrowthService());

        $npcProfiles = $this->createMock(NpcProfileRepository::class);
        $npcProfiles->expects(self::once())
            ->method('findByWorld')
            ->with($world)
            ->willReturn([]);

        $tiles = $this->createMock(WorldMapTileRepository::class);
        $tiles->expects(self::once())
            ->method('findBy')
            ->with(['world' => $world, 'hasDojo' => true])
            ->willReturn([]);

        $handler = new AdvanceDayHandler($worldRepository, $characterRepository, $npcProfiles, $tiles, $clock, $entityManager);

        $beforeStrength = $character->getStrength();

        $result = $handler->advance(1, 1);

        self::assertSame(1, $result->daysAdvanced);
        self::assertSame(1, $result->world->getCurrentDay());
        self::assertGreaterThan($beforeStrength, $character->getStrength());
    }

    public function testAdvanceMovesTravelingCharacterInsteadOfTraining(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'Bulma', Race::Human);
        $character->setTravelTarget(1, 0);

        $worldRepository = $this->createMock(WorldRepository::class);
        $worldRepository->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($world);

        $characterRepository = $this->createMock(CharacterRepository::class);
        $characterRepository->expects(self::once())
            ->method('findBy')
            ->with(['world' => $world])
            ->willReturn([$character]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $clock   = new SimulationClock(new TrainingGrowthService());

        $npcProfiles = $this->createMock(NpcProfileRepository::class);
        $npcProfiles->expects(self::once())
            ->method('findByWorld')
            ->with($world)
            ->willReturn([]);

        $tiles = $this->createMock(WorldMapTileRepository::class);
        $tiles->expects(self::once())
            ->method('findBy')
            ->with(['world' => $world, 'hasDojo' => true])
            ->willReturn([]);

        $handler = new AdvanceDayHandler($worldRepository, $characterRepository, $npcProfiles, $tiles, $clock, $entityManager);

        $beforeStrength = $character->getStrength();

        $handler->advance(1, 1);

        self::assertSame(1, $character->getTileX());
        self::assertSame(0, $character->getTileY());
        self::assertFalse($character->hasTravelTarget());
        self::assertSame($beforeStrength, $character->getStrength());
    }
}
