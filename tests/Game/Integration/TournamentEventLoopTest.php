<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\NpcProfile;
use App\Entity\Settlement;
use App\Entity\Tournament;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Npc\NpcArchetype;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TournamentEventLoopTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testTournamentAnnouncementTriggersFighterGoalOverrideNextDay(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $world->setMapSize(8, 8);

        $settlementTile = new WorldMapTile($world, 3, 0, Biome::City);
        $settlementTile->setHasSettlement(true);

        $settlement = new Settlement($world, 3, 0);
        $settlement->addToTreasury(1_000);

        $organizer = new Character($world, 'Announcer', Race::Human);
        $organizer->setTilePosition(3, 0);

        $fighter = new Character($world, 'Fighter', Race::Human);
        $fighter->setTilePosition(2, 0);
        $fighterProfile = new NpcProfile($fighter, NpcArchetype::Fighter);

        $organizerGoal = new CharacterGoal($organizer);
        $organizerGoal->setLifeGoalCode('civilian.organize_events');
        $organizerGoal->setCurrentGoalCode('goal.organize_tournament');
        $organizerGoal->setCurrentGoalData(['spend' => 200]);
        $organizerGoal->setCurrentGoalComplete(false);

        $fighterGoal = new CharacterGoal($fighter);
        $fighterGoal->setLifeGoalCode('fighter.become_strongest');
        $fighterGoal->setCurrentGoalCode('goal.train_in_dojo');
        $fighterGoal->setCurrentGoalData(['target_days' => 7, 'days_trained' => 0]);
        $fighterGoal->setCurrentGoalComplete(true);
        $fighterGoal->setLastResolvedDay(1); // ensure day 1 doesn't repick goals before the event exists

        $entityManager->persist($world);
        $entityManager->persist($settlementTile);
        $entityManager->persist($settlement);
        $entityManager->persist($organizer);
        $entityManager->persist($fighter);
        $entityManager->persist($fighterProfile);
        $entityManager->persist($organizerGoal);
        $entityManager->persist($fighterGoal);
        $entityManager->flush();

        $handler = self::getContainer()->get(AdvanceDayHandler::class);
        self::assertInstanceOf(AdvanceDayHandler::class, $handler);

        $handler->advance((int)$world->getId(), 2);

        /** @var EntityManagerInterface $entityManager */
        $entityManager->refresh($world);
        $entityManager->refresh($fighter);
        $entityManager->refresh($fighterGoal);

        $event = $entityManager->getRepository(CharacterEvent::class)->findOneBy([
            'world' => $world,
            'type'  => 'tournament_announced',
            'day'   => 1,
        ]);
        self::assertInstanceOf(CharacterEvent::class, $event);
        self::assertNull($event->getCharacter());
        self::assertSame([
            'announce_day'           => 1,
            'registration_close_day' => 2,
            'center_x'        => 3,
            'center_y'        => 0,
            'radius'          => 6,
            'spend'           => 200,
            'prize_pool'      => 100,
            'prize_1'         => 50,
            'prize_2'         => 30,
            'prize_3'         => 20,
            'fame_gain'       => 3,
            'prosperity_gain' => 2,
            'resolve_day'     => 3,
        ], $event->getData());

        $tournament = $entityManager->getRepository(Tournament::class)->findOneBy(['requestEventId' => (int)$event->getId()]);
        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertSame(1, $tournament->getAnnounceDay());
        self::assertSame(3, $tournament->getResolveDay());
        self::assertSame(200, $tournament->getSpend());
        self::assertSame(100, $tournament->getPrizePool());
        self::assertSame(6, $tournament->getRadius());

        $committed = $entityManager->getRepository(CharacterEvent::class)->findOneBy([
            'world' => $world,
            'character' => $fighter,
            'type' => 'tournament_interest_committed',
            'day' => 1,
        ]);
        self::assertInstanceOf(CharacterEvent::class, $committed);

        self::assertSame('goal.participate_tournament', $fighterGoal->getCurrentGoalCode());
        self::assertFalse($fighterGoal->isCurrentGoalComplete());

        self::assertGreaterThanOrEqual(2, $fighter->getTileX());
        self::assertSame(0, $fighter->getTileY());
    }
}
