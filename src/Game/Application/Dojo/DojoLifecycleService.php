<?php

namespace App\Game\Application\Dojo;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterTechnique;
use App\Entity\CharacterTransformation;
use App\Entity\Settlement;
use App\Entity\SettlementBuilding;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Settlement\ProjectCatalogProviderInterface;
use App\Game\Domain\Combat\SimulatedCombat\CombatRules;
use App\Game\Domain\Combat\SimulatedCombat\SimulatedCombatant;
use App\Game\Domain\Combat\SimulatedCombat\SimulatedCombatResolver;
use App\Repository\SettlementBuildingRepository;
use App\Repository\WorldMapTileRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DojoLifecycleService
{
    public function __construct(
        private readonly EntityManagerInterface          $entityManager,
        private readonly SettlementBuildingRepository    $buildings,
        private readonly WorldMapTileRepository          $tiles,
        private readonly ProjectCatalogProviderInterface $projectCatalogProvider,
        private readonly ?SimulatedCombatResolver $combatResolver = null,
    )
    {
    }

    /**
     * @param list<Settlement>     $settlements
     * @param list<Character>      $characters
     * @param list<CharacterEvent> $emittedEvents
     *
     * @return list<CharacterEvent> events emitted by dojo lifecycle (persisted by caller)
     */
    public function advanceDay(
        World $world,
        int   $worldDay,
        array $settlements,
        array $characters,
        array $emittedEvents,
    ): array
    {
        if ($worldDay < 0) {
            throw new \InvalidArgumentException('worldDay must be >= 0.');
        }

        if ($settlements === [] || $emittedEvents === []) {
            return [];
        }

        $settlementsByCoord = [];
        foreach ($settlements as $s) {
            $settlementsByCoord[sprintf('%d:%d', $s->getX(), $s->getY())] = $s;
        }

        $charactersById = [];
        foreach ($characters as $c) {
            $id = $c->getId();
            if ($id !== null) {
                $charactersById[(int)$id] = $c;
            }
        }

        $cooldownDays = $this->projectCatalogProvider->get()->dojoChallengeCooldownDays();
        if ($cooldownDays < 1) {
            $cooldownDays = 1;
        }

        /** @var array<string,SettlementBuilding|null> $dojoBySettlementKey */
        $dojoBySettlementKey = [];

        $events = [];

        foreach ($emittedEvents as $event) {
            $data = $event->getData();
            if (!is_array($data)) {
                continue;
            }

            $x = $data['settlement_x'] ?? null;
            $y = $data['settlement_y'] ?? null;
            if (!is_int($x) || !is_int($y) || $x < 0 || $y < 0) {
                continue;
            }

            $settlement = $settlementsByCoord[sprintf('%d:%d', $x, $y)] ?? null;
            if (!$settlement instanceof Settlement) {
                continue;
            }

            if ($event->getType() === 'dojo_claim_requested') {
                $claimant = $event->getCharacter();
                if (!$claimant instanceof Character) {
                    continue;
                }
                if ($claimant->getTileX() !== $x || $claimant->getTileY() !== $y) {
                    continue;
                }

                $dojo = $this->ensureDojoBuilding($world, $settlement, $x, $y, $dojoBySettlementKey);
                if (!$dojo instanceof SettlementBuilding || $dojo->getLevel() <= 0) {
                    continue;
                }
                if ($dojo->getMasterCharacter() !== null) {
                    continue;
                }

                $dojo->setMasterCharacter($claimant);

                $events[] = new CharacterEvent(
                    world: $world,
                    character: null,
                    type: 'dojo_claimed',
                    day: $worldDay,
                    data: [
                        'settlement_x' => $x,
                        'settlement_y' => $y,
                        'master_id'    => (int)$claimant->getId(),
                    ],
                );

                continue;
            }

            if ($event->getType() === 'dojo_challenge_requested') {
                $challenger = $event->getCharacter();
                if (!$challenger instanceof Character) {
                    continue;
                }
                if ($challenger->getTileX() !== $x || $challenger->getTileY() !== $y) {
                    continue;
                }

                $dojo = $this->ensureDojoBuilding($world, $settlement, $x, $y, $dojoBySettlementKey);
                if (!$dojo instanceof SettlementBuilding || $dojo->getLevel() <= 0) {
                    continue;
                }

                $master = $dojo->getMasterCharacter();
                if (!$master instanceof Character) {
                    continue;
                }

                $masterId     = $master->getId();
                $challengerId = $challenger->getId();
                if ($masterId === null || $challengerId === null) {
                    continue;
                }

                $masterLive = $charactersById[(int)$masterId] ?? null;
                if ($masterLive instanceof Character) {
                    $master = $masterLive;
                }

                if ($master->getTileX() !== $x || $master->getTileY() !== $y) {
                    continue;
                }

                $last = $dojo->getMasterLastChallengedDay();
                if ($last >= 0 && ($worldDay - $last) < $cooldownDays) {
                    continue;
                }

                $dojo->setMasterLastChallengedDay($worldDay);

                /** @var list<CharacterTechnique> $challengerTech */
                $challengerTech = $this->entityManager->getRepository(CharacterTechnique::class)->findBy(['character' => $challenger], ['id' => 'ASC']);
                /** @var list<CharacterTechnique> $masterTech */
                $masterTech = $this->entityManager->getRepository(CharacterTechnique::class)->findBy(['character' => $master], ['id' => 'ASC']);

                /** @var list<CharacterTransformation> $challengerTrans */
                $challengerTrans = $this->entityManager->getRepository(CharacterTransformation::class)->findBy(['character' => $challenger], ['id' => 'ASC']);
                /** @var list<CharacterTransformation> $masterTrans */
                $masterTrans = $this->entityManager->getRepository(CharacterTransformation::class)->findBy(['character' => $master], ['id' => 'ASC']);

                $resolver = $this->combatResolver ?? new SimulatedCombatResolver();

                $result = $resolver->resolve(
                    combatants: [
                        new SimulatedCombatant($challenger, teamId: (int)$challengerId, techniques: $challengerTech, transformations: $challengerTrans),
                        new SimulatedCombatant($master, teamId: (int)$masterId, techniques: $masterTech, transformations: $masterTrans),
                    ],
                    rules: new CombatRules(allowFriendlyFire: true),
                );

                $winner = $result->winnerCharacterId === (int)$challengerId ? $challenger : $master;

                $events[] = new CharacterEvent(
                    world: $world,
                    character: null,
                    type: 'sim_fight_resolved',
                    day: $worldDay,
                    data: [
                        'context'       => 'dojo_challenge',
                        'settlement_x'  => $x,
                        'settlement_y'  => $y,
                        'challenger_id' => (int)$challengerId,
                        'master_id'     => (int)$masterId,
                        'winner_id'     => (int)$winner->getId(),
                        'actions'       => $result->actions,
                    ],
                );

                if ($winner === $challenger) {
                    $dojo->setMasterCharacter($challenger);
                }

                $events[] = new CharacterEvent(
                    world: $world,
                    character: null,
                    type: 'dojo_master_changed',
                    day: $worldDay,
                    data: [
                        'settlement_x'    => $x,
                        'settlement_y'    => $y,
                        'previous_master' => (int)$masterId,
                        'new_master'      => $winner === $challenger ? (int)$challengerId : (int)$masterId,
                    ],
                );
            }
        }

        return $events;
    }

    /**
     * @param array<string,SettlementBuilding|null> $dojoBySettlementKey
     */
    private function ensureDojoBuilding(World $world, Settlement $settlement, int $x, int $y, array &$dojoBySettlementKey): ?SettlementBuilding
    {
        $sid = $settlement->getId();
        $key = $sid !== null ? sprintf('id:%d', (int)$sid) : sprintf('coord:%d:%d', $x, $y);
        if (array_key_exists($key, $dojoBySettlementKey)) {
            return $dojoBySettlementKey[$key];
        }

        $dojo = $this->buildings->findOneBySettlementAndCode($settlement, 'dojo');
        if ($dojo instanceof SettlementBuilding) {
            $dojoBySettlementKey[$key] = $dojo;
            return $dojo;
        }

        $tile = $this->tiles->findOneBy(['world' => $world, 'x' => $x, 'y' => $y]);
        if (!$tile instanceof WorldMapTile || !$tile->hasDojo()) {
            $dojoBySettlementKey[$key] = null;
            return null;
        }

        $dojo = new SettlementBuilding($settlement, 'dojo', 1);
        $this->entityManager->persist($dojo);

        $dojoBySettlementKey[$key] = $dojo;

        return $dojo;
    }
}
