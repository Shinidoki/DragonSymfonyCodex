<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\CharacterGoal;
use App\Entity\Settlement;
use App\Entity\Tournament;
use App\Entity\TournamentParticipant;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TournamentGoalUnlockOnEliminationTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testEliminatedParticipantsCompleteGoalAfterGroupStage(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $world->setMapSize(8, 8);
        $entityManager->persist($world);

        $settlementTile = new WorldMapTile($world, 3, 0, Biome::City);
        $settlementTile->setHasSettlement(true);

        $settlement = new Settlement($world, 3, 0);
        $settlement->addToTreasury(5_000);

        $organizer = new Character($world, 'Announcer', Race::Human);
        $organizer->setTilePosition(3, 0);

        $organizerGoal = new CharacterGoal($organizer);
        $organizerGoal->setLifeGoalCode('civilian.organize_events');
        $organizerGoal->setCurrentGoalCode('goal.organize_tournament');
        $organizerGoal->setCurrentGoalData(['spend' => 200]);
        $organizerGoal->setCurrentGoalComplete(false);

        $fighters     = [];
        $fighterGoals = [];

        for ($i = 1; $i <= 5; $i++) {
            $c = new Character($world, sprintf('F%d', $i), Race::Human);
            $c->setTilePosition(3, 0);
            $fighters[] = $c;

            $g = new CharacterGoal($c);
            $g->setLifeGoalCode('fighter.become_strongest');
            $g->setCurrentGoalCode('goal.participate_tournament');
            $g->setCurrentGoalData(['center_x' => 3, 'center_y' => 0, 'resolve_day' => 3]);
            $g->setCurrentGoalComplete(false);
            $fighterGoals[] = $g;

            $entityManager->persist($c);
            $entityManager->persist($g);
        }

        $entityManager->persist($settlementTile);
        $entityManager->persist($settlement);
        $entityManager->persist($organizer);
        $entityManager->persist($organizerGoal);
        $entityManager->flush();

        $handler = self::getContainer()->get(AdvanceDayHandler::class);
        self::assertInstanceOf(AdvanceDayHandler::class, $handler);

        // Day 1: organizer announces; participants register. Day 2: group stage runs.
        $handler->advance((int)$world->getId(), 2);

        $tournament = $entityManager->getRepository(Tournament::class)->findOneBy(['world' => $world]);
        self::assertInstanceOf(Tournament::class, $tournament);

        $participants = $entityManager->getRepository(TournamentParticipant::class)->findBy(['tournament' => $tournament]);
        self::assertCount(5, $participants);

        $eliminatedIds = [];
        foreach ($participants as $p) {
            if ($p->getStatus() === TournamentParticipant::STATUS_ELIMINATED && $p->getEliminatedDay() === 2) {
                $eliminatedIds[] = (int)$p->getCharacter()->getId();
            }
        }
        self::assertNotEmpty($eliminatedIds, 'At least one participant should be eliminated in group stage when 5 participate.');

        $completed  = 0;
        $incomplete = 0;
        foreach ($fighterGoals as $g) {
            $entityManager->refresh($g);
            if ($g->isCurrentGoalComplete()) {
                $completed++;
            } else {
                $incomplete++;
            }
        }

        self::assertGreaterThanOrEqual(1, $completed);
        self::assertGreaterThanOrEqual(1, $incomplete);

        foreach ($fighterGoals as $g) {
            $cid = (int)$g->getCharacter()->getId();
            if (in_array($cid, $eliminatedIds, true)) {
                self::assertTrue($g->isCurrentGoalComplete(), 'Eliminated participants must unlock immediately (goal complete).');
            }
        }
    }
}

