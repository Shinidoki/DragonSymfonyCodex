<?php

declare(strict_types=1);

namespace App\Tests\Game\Application\Tournament;

use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\World;
use App\Game\Application\Economy\EconomyCatalogProviderInterface;
use App\Game\Application\Tournament\TournamentDemandFeedbackService;
use App\Game\Domain\Economy\EconomyCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class TournamentDemandFeedbackServiceTest extends TestCase
{
    public function testReturnsPositiveAdjustmentWhenRecentOutcomesAreStrong(): void
    {
        $world = new World('seed-1');
        $settlement = new Settlement($world, 10, 12);

        $events = [
            $this->outcome($world, 20, 'tournament_resolved', 10, 12),
            $this->outcome($world, 19, 'tournament_resolved', 10, 12),
            $this->outcome($world, 18, 'tournament_canceled', 10, 12),
        ];

        $service = new TournamentDemandFeedbackService(
            entityManager: $this->mockEntityManager($events),
            economyCatalogProvider: $this->provider($this->economyCatalog()),
        );

        $feedback = $service->forSettlement($world, $settlement, 20);

        self::assertSame(3, $feedback['sampleSize']);
        self::assertSame(1.1, $feedback['spendMultiplier']);
        self::assertSame(1, $feedback['radiusDelta']);
    }

    public function testReturnsNeutralAdjustmentBelowMinimumSample(): void
    {
        $world = new World('seed-1');
        $settlement = new Settlement($world, 10, 12);

        $events = [
            $this->outcome($world, 20, 'tournament_resolved', 10, 12),
        ];

        $service = new TournamentDemandFeedbackService(
            entityManager: $this->mockEntityManager($events),
            economyCatalogProvider: $this->provider($this->economyCatalog()),
        );

        $feedback = $service->forSettlement($world, $settlement, 20);

        self::assertSame(1, $feedback['sampleSize']);
        self::assertSame(1.0, $feedback['spendMultiplier']);
        self::assertSame(0, $feedback['radiusDelta']);
    }

    public function testClampsNegativeAdjustmentToConfiguredBounds(): void
    {
        $world = new World('seed-1');
        $settlement = new Settlement($world, 10, 12);

        $events = [
            $this->outcome($world, 20, 'tournament_canceled', 10, 12),
            $this->outcome($world, 19, 'tournament_canceled', 10, 12),
            $this->outcome($world, 18, 'tournament_canceled', 10, 12),
            $this->outcome($world, 17, 'tournament_canceled', 10, 12),
            $this->outcome($world, 16, 'tournament_canceled', 10, 12),
        ];

        $service = new TournamentDemandFeedbackService(
            entityManager: $this->mockEntityManager($events),
            economyCatalogProvider: $this->provider($this->economyCatalog()),
        );

        $feedback = $service->forSettlement($world, $settlement, 20);

        self::assertSame(5, $feedback['sampleSize']);
        self::assertSame(0.7, $feedback['spendMultiplier']);
        self::assertSame(-3, $feedback['radiusDelta']);
    }

    /** @param list<CharacterEvent> $events */
    private function mockEntityManager(array $events): EntityManagerInterface
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($events);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }

    private function provider(EconomyCatalog $catalog): EconomyCatalogProviderInterface
    {
        return new class ($catalog) implements EconomyCatalogProviderInterface {
            public function __construct(private readonly EconomyCatalog $catalog)
            {
            }

            public function get(): EconomyCatalog
            {
                return $this->catalog;
            }
        };
    }

    private function economyCatalog(): EconomyCatalog
    {
        return new EconomyCatalog(
            jobs: [],
            employmentPools: [],
            settlement: ['wage_pool_rate' => 0.7, 'tax_rate' => 0.2, 'production' => ['per_work_unit_base' => 10, 'per_work_unit_prosperity_mult' => 1, 'randomness_pct' => 0.1]],
            thresholds: ['money_low_employed' => 10, 'money_low_unemployed' => 5],
            tournaments: [
                'min_spend' => 50,
                'max_spend_fraction_of_treasury' => 0.3,
                'prize_pool_fraction' => 0.5,
                'duration_days' => 2,
                'radius' => ['base' => 2, 'per_spend' => 50, 'max' => 20],
                'gains' => ['fame_base' => 1, 'fame_per_spend' => 100, 'prosperity_base' => 1, 'prosperity_per_spend' => 150, 'per_participant_fame' => 1],
                'tournament_feedback' => [
                    'lookback_days' => 14,
                    'sample_size_min' => 2,
                    'spend_multiplier_step' => 0.1,
                    'radius_delta_step' => 1,
                    'spend_multiplier_min' => 0.7,
                    'spend_multiplier_max' => 1.3,
                    'radius_delta_min' => -3,
                    'radius_delta_max' => 3,
                ],
            ],
            tournamentInterest: [],
        );
    }

    private function outcome(World $world, int $day, string $type, int $x, int $y): CharacterEvent
    {
        return new CharacterEvent(
            world: $world,
            character: null,
            type: $type,
            day: $day,
            data: ['center_x' => $x, 'center_y' => $y],
        );
    }
}
