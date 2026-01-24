<?php

namespace App\Tests\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\World;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\Handlers\OrganizeTournamentGoalHandler;
use App\Game\Domain\Race;
use PHPUnit\Framework\TestCase;

final class OrganizeTournamentGoalHandlerTest extends TestCase
{
    public function testEmitsTournamentAnnouncementEventAndCompletes(): void
    {
        $world = new World('seed-1');
        $world->advanceDays(1); // day = 1

        $character = new Character($world, 'Announcer', Race::Human);
        $character->setTilePosition(3, 7);

        $handler = new OrganizeTournamentGoalHandler();
        $result  = $handler->step($character, $world, ['radius' => 5], new GoalContext([]));

        self::assertTrue($result->completed);
        self::assertCount(1, $result->events);

        $event = $result->events[0];
        self::assertInstanceOf(CharacterEvent::class, $event);
        self::assertSame('tournament_announced', $event->getType());
        self::assertSame(1, $event->getDay());
        self::assertNull($event->getCharacter());
        self::assertSame(['center_x' => 3, 'center_y' => 7, 'radius' => 5], $event->getData());
    }
}

