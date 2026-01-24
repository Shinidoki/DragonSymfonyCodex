<?php

namespace App\Game\Application\World;

use App\Entity\Character;
use App\Entity\CharacterGoal;
use App\Entity\NpcProfile;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Domain\Npc\NpcArchetype;
use App\Game\Domain\Race;
use App\Repository\NpcProfileRepository;
use App\Repository\WorldMapTileRepository;
use App\Repository\WorldRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PopulateWorldHandler
{
    public function __construct(
        private readonly WorldRepository        $worldRepository,
        private readonly WorldMapTileRepository $tiles,
        private readonly NpcProfileRepository   $npcProfiles,
        private readonly NpcLifeGoalPicker $lifeGoalPicker,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function populate(int $worldId, int $count): PopulateWorldResult
    {
        if ($worldId <= 0) {
            throw new \InvalidArgumentException('worldId must be positive.');
        }
        if ($count <= 0) {
            throw new \InvalidArgumentException('count must be positive.');
        }

        $world = $this->worldRepository->find($worldId);
        if (!$world instanceof World) {
            throw new \RuntimeException(sprintf('World not found: %d', $worldId));
        }
        if ($world->getWidth() <= 0 || $world->getHeight() <= 0) {
            throw new \RuntimeException('World map must be generated before populating NPCs.');
        }

        $existing = $this->npcProfiles->countForWorld($world);

        /** @var list<WorldMapTile> $settlements */
        $settlements = $this->tiles->findBy(['world' => $world, 'hasSettlement' => true]);

        /** @var list<WorldMapTile> $dojos */
        $dojos = $this->tiles->findBy(['world' => $world, 'hasDojo' => true]);

        $createdByArchetype = [
            NpcArchetype::Civilian->value => 0,
            NpcArchetype::Fighter->value  => 0,
            NpcArchetype::Wanderer->value => 0,
        ];

        for ($i = 1; $i <= $count; $i++) {
            $index = $existing + $i;

            $name      = sprintf('NPC-%04d', $index);
            $race      = $this->pickRace($world->getSeed(), $index);
            $archetype = $this->pickArchetype($world->getSeed(), $index);
            [$x, $y] = $this->pickStartPosition($world, $index, $archetype, $settlements, $dojos);

            $character = new Character($world, $name, $race);
            $character->setTilePosition($x, $y);

            $profile = new NpcProfile($character, $archetype);

            $goals = new CharacterGoal($character);
            $goals->setLifeGoalCode($this->lifeGoalPicker->pickForArchetype($archetype->value));

            $this->entityManager->persist($character);
            $this->entityManager->persist($profile);
            $this->entityManager->persist($goals);

            $createdByArchetype[$archetype->value]++;
        }

        $this->entityManager->flush();

        return new PopulateWorldResult($world, $count, $createdByArchetype);
    }

    private function pickRace(string $worldSeed, int $index): Race
    {
        $races = Race::cases();
        $n     = $this->hashInt(sprintf('%s:race:%d', $worldSeed, $index));

        return $races[$n % count($races)];
    }

    private function pickArchetype(string $worldSeed, int $index): NpcArchetype
    {
        $roll = $this->hashInt(sprintf('%s:archetype:%d', $worldSeed, $index)) % 100;

        return match (true) {
            $roll < 10 => NpcArchetype::Wanderer,
            $roll < 40 => NpcArchetype::Fighter,
            default => NpcArchetype::Civilian,
        };
    }

    /**
     * @param list<WorldMapTile> $settlements
     * @param list<WorldMapTile> $dojos
     *
     * @return array{0:int,1:int}
     */
    private function pickStartPosition(
        World        $world,
        int          $index,
        NpcArchetype $archetype,
        array        $settlements,
        array        $dojos,
    ): array
    {
        if ($archetype === NpcArchetype::Fighter && count($dojos) > 0) {
            $tile = $dojos[$this->hashInt(sprintf('%s:dojo-start:%d', $world->getSeed(), $index)) % count($dojos)];
            return [$tile->getX(), $tile->getY()];
        }

        if (count($settlements) > 0) {
            $tile = $settlements[$this->hashInt(sprintf('%s:settlement-start:%d', $world->getSeed(), $index)) % count($settlements)];
            return [$tile->getX(), $tile->getY()];
        }

        $x = $this->hashInt(sprintf('%s:x:%d', $world->getSeed(), $index)) % $world->getWidth();
        $y = $this->hashInt(sprintf('%s:y:%d', $world->getSeed(), $index)) % $world->getHeight();

        return [$x, $y];
    }

    private function hashInt(string $input): int
    {
        $hash = hash('sha256', $input);

        return (int)hexdec(substr($hash, 0, 8));
    }
}
