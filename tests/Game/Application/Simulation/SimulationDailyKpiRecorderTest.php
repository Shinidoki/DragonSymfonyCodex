<?php

declare(strict_types=1);

namespace App\Tests\Game\Application\Simulation;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\World;
use App\Game\Application\Simulation\SimulationDailyKpiRecorder;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SimulationDailyKpiRecorderTest extends TestCase
{
    public function testRecordsDailyKpisFromWorldStateAndEvents(): void
    {
        $world = new World('seed-1');

        $c1 = new Character($world, 'A', Race::Human);
        $c2 = new Character($world, 'B', Race::Human);
        $c3 = new Character($world, 'C', Race::Human);

        $c1->setEmployment('laborer', 0, 0);
        $c2->setEmployment('laborer', 0, 0);

        $s1 = new Settlement($world, 0, 0);
        $s1->setProsperity(40);
        $s1->addToTreasury(100);

        $s2 = new Settlement($world, 4, 0);
        $s2->setProsperity(60);
        $s2->addToTreasury(200);

        $events = [
            new CharacterEvent($world, null, 'settlement_migration_committed', 1),
            new CharacterEvent($world, null, 'tournament_announced', 1),
            new CharacterEvent($world, null, 'tournament_resolved', 1),
            new CharacterEvent($world, null, 'tournament_canceled', 1),
            new CharacterEvent($world, null, 'other', 1),
        ];

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });

        $recorder = new SimulationDailyKpiRecorder($em);
        $recorder->recordDay(
            world: $world,
            day: 1,
            characters: [$c1, $c2, $c3],
            settlements: [$s1, $s2],
            emittedEvents: $events,
        );

        self::assertCount(1, $persisted);
        $kpi = $persisted[0];
        self::assertSame(2, $kpi->getSettlementsActive());
        self::assertSame(3, $kpi->getPopulationTotal());
        self::assertSame(1, $kpi->getUnemployedCount());
        self::assertSame(1.0 / 3.0, $kpi->getUnemploymentRate());
        self::assertSame(1, $kpi->getMigrationCommits());
        self::assertSame(1, $kpi->getTournamentAnnounced());
        self::assertSame(1, $kpi->getTournamentResolved());
        self::assertSame(1, $kpi->getTournamentCanceled());
        self::assertSame(50.0, $kpi->getMeanSettlementProsperity());
        self::assertSame(150.0, $kpi->getMeanSettlementTreasury());
    }
}
