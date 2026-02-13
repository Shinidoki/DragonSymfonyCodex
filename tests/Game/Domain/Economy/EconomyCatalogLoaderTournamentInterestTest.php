<?php

declare(strict_types=1);

namespace App\Tests\Game\Domain\Economy;

use App\Game\Domain\Economy\EconomyCatalogLoader;
use PHPUnit\Framework\TestCase;

final class EconomyCatalogLoaderTournamentInterestTest extends TestCase
{
    public function testLoadsTournamentInterestConfig(): void
    {
        $yaml = <<<'YAML'
settlement:
  wage_pool_rate: 0.7
  tax_rate: 0.2
  production:
    per_work_unit_base: 10
    per_work_unit_prosperity_mult: 1
    randomness_pct: 0.1
thresholds:
  money_low_employed: 10
  money_low_unemployed: 5
jobs:
  laborer: { label: Laborer, wage_weight: 1, work_radius: 2 }
employment_pools:
  civilian: [ { code: laborer, weight: 1 } ]
tournaments:
  min_spend: 50
  max_spend_fraction_of_treasury: 0.3
  prize_pool_fraction: 0.5
  duration_days: 2
  radius: { base: 2, per_spend: 50, max: 20 }
  gains:
    fame_base: 1
    fame_per_spend: 100
    prosperity_base: 1
    prosperity_per_spend: 150
    per_participant_fame: 1
tournament_interest:
  commit_threshold: 60
  weights:
    distance: 30
    prize_pool: 25
    archetype_bias: 20
    money_pressure: 15
    cooldown_penalty: 20
YAML;

        $path = $this->writeTempYaml($yaml);

        $catalog = (new EconomyCatalogLoader())->loadFromFile($path);

        self::assertSame(60, $catalog->tournamentInterestCommitThreshold());
        self::assertSame(30, $catalog->tournamentInterestWeightDistance());
        self::assertSame(25, $catalog->tournamentInterestWeightPrizePool());
        self::assertSame(20, $catalog->tournamentInterestWeightArchetypeBias());
        self::assertSame(15, $catalog->tournamentInterestWeightMoneyPressure());
        self::assertSame(20, $catalog->tournamentInterestWeightCooldownPenalty());
    }

    public function testRejectsInvalidTournamentInterestCommitThreshold(): void
    {
        $yaml = <<<'YAML'
settlement:
  wage_pool_rate: 0.7
  tax_rate: 0.2
  production:
    per_work_unit_base: 10
    per_work_unit_prosperity_mult: 1
    randomness_pct: 0.1
thresholds:
  money_low_employed: 10
  money_low_unemployed: 5
jobs:
  laborer: { label: Laborer, wage_weight: 1, work_radius: 2 }
employment_pools:
  civilian: [ { code: laborer, weight: 1 } ]
tournaments:
  min_spend: 50
  max_spend_fraction_of_treasury: 0.3
  prize_pool_fraction: 0.5
  duration_days: 2
  radius: { base: 2, per_spend: 50, max: 20 }
  gains:
    fame_base: 1
    fame_per_spend: 100
    prosperity_base: 1
    prosperity_per_spend: 150
    per_participant_fame: 1
tournament_interest:
  commit_threshold: 120
  weights:
    distance: 30
    prize_pool: 25
    archetype_bias: 20
    money_pressure: 15
    cooldown_penalty: 20
YAML;

        $path = $this->writeTempYaml($yaml);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tournament_interest.commit_threshold must be an integer between 0 and 100.');

        (new EconomyCatalogLoader())->loadFromFile($path);
    }

    private function writeTempYaml(string $yaml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'econ_');
        if ($path === false) {
            self::fail('Failed to create temp file.');
        }

        file_put_contents($path, $yaml);

        return $path;
    }
}
