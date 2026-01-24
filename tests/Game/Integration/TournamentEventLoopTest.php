<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\World;
use App\Game\Application\Simulation\AdvanceDayHandler;
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

        $organizer = new Character($world, 'Announcer', Race::Human);
        $organizer->setTilePosition(3, 0);

        $fighter = new Character($world, 'Fighter', Race::Human);
        $fighter->setTilePosition(0, 0);

        $organizerGoal = new CharacterGoal($organizer);
        $organizerGoal->setLifeGoalCode('civilian.organize_events');
        $organizerGoal->setCurrentGoalCode('goal.organize_tournament');
        $organizerGoal->setCurrentGoalData(['radius' => 10]);
        $organizerGoal->setCurrentGoalComplete(false);

        $fighterGoal = new CharacterGoal($fighter);
        $fighterGoal->setLifeGoalCode('fighter.become_strongest');
        $fighterGoal->setCurrentGoalCode('goal.train_in_dojo');
        $fighterGoal->setCurrentGoalData(['target_days' => 7, 'days_trained' => 0]);
        $fighterGoal->setCurrentGoalComplete(true);
        $fighterGoal->setLastResolvedDay(1); // ensure day 1 doesn't repick goals before the event exists

        $entityManager->persist($world);
        $entityManager->persist($organizer);
        $entityManager->persist($fighter);
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
        self::assertSame(['center_x' => 3, 'center_y' => 0, 'radius' => 10], $event->getData());

        self::assertSame('goal.participate_tournament', $fighterGoal->getCurrentGoalCode());
        self::assertFalse($fighterGoal->isCurrentGoalComplete());

        self::assertSame(1, $fighter->getTileX());
        self::assertSame(0, $fighter->getTileY());
        self::assertTrue($fighter->hasTravelTarget());
        self::assertSame(3, $fighter->getTargetTileX());
        self::assertSame(0, $fighter->getTargetTileY());
    }
}
