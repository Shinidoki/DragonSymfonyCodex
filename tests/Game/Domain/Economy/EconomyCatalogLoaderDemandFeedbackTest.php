<?php

declare(strict_types=1);

namespace App\Tests\Game\Domain\Economy;

use App\Game\Domain\Economy\EconomyCatalogLoader;
use PHPUnit\Framework\TestCase;

final class EconomyCatalogLoaderDemandFeedbackTest extends TestCase
{
    public function testLoadsTournamentDemandFeedbackConfig(): void
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
  tournament_feedback:
    lookback_days: 10
    sample_size_min: 2
    spend_multiplier_step: 0.1
    radius_delta_step: 1
    spend_multiplier_min: 0.7
    spend_multiplier_max: 1.3
    radius_delta_min: -3
    radius_delta_max: 3
YAML;

        $path = $this->writeTempYaml($yaml);

        $catalog = (new EconomyCatalogLoader())->loadFromFile($path);

        self::assertSame(10, $catalog->tournamentFeedbackLookbackDays());
        self::assertSame(2, $catalog->tournamentFeedbackSampleSizeMin());
        self::assertSame(0.1, $catalog->tournamentFeedbackSpendMultiplierStep());
        self::assertSame(1, $catalog->tournamentFeedbackRadiusDeltaStep());
        self::assertSame(0.7, $catalog->tournamentFeedbackSpendMultiplierMin());
        self::assertSame(1.3, $catalog->tournamentFeedbackSpendMultiplierMax());
        self::assertSame(-3, $catalog->tournamentFeedbackRadiusDeltaMin());
        self::assertSame(3, $catalog->tournamentFeedbackRadiusDeltaMax());
    }

    public function testUsesNeutralTournamentDemandFeedbackDefaultsWhenMissing(): void
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
YAML;

        $path = $this->writeTempYaml($yaml);

        $catalog = (new EconomyCatalogLoader())->loadFromFile($path);

        self::assertSame(14, $catalog->tournamentFeedbackLookbackDays());
        self::assertSame(2, $catalog->tournamentFeedbackSampleSizeMin());
        self::assertSame(0.1, $catalog->tournamentFeedbackSpendMultiplierStep());
        self::assertSame(1, $catalog->tournamentFeedbackRadiusDeltaStep());
        self::assertSame(0.7, $catalog->tournamentFeedbackSpendMultiplierMin());
        self::assertSame(1.3, $catalog->tournamentFeedbackSpendMultiplierMax());
        self::assertSame(-3, $catalog->tournamentFeedbackRadiusDeltaMin());
        self::assertSame(3, $catalog->tournamentFeedbackRadiusDeltaMax());
    }

    public function testRejectsInvalidTournamentDemandFeedbackSpendBounds(): void
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
  tournament_feedback:
    lookback_days: 10
    sample_size_min: 2
    spend_multiplier_step: 0.1
    radius_delta_step: 1
    spend_multiplier_min: 1.4
    spend_multiplier_max: 1.3
    radius_delta_min: -3
    radius_delta_max: 3
YAML;

        $path = $this->writeTempYaml($yaml);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tournaments.tournament_feedback.spend_multiplier_min must be <= spend_multiplier_max.');

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
