<?php

declare(strict_types=1);

namespace App\Tests\Game\Domain\Economy;

use App\Game\Domain\Economy\EconomyCatalogLoader;
use PHPUnit\Framework\TestCase;

final class EconomyCatalogLoaderMigrationPressureTest extends TestCase
{
    public function testLoadsMigrationPressureConfig(): void
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
migration_pressure:
  lookback_days: 7
  commit_threshold: 60
  move_cooldown_days: 5
  daily_move_cap: 3
  max_travel_distance: 12
  weights:
    prosperity_gap: 30
    treasury_gap: 20
    crowding_gap: 15
YAML;

        $path = $this->writeTempYaml($yaml);

        $catalog = (new EconomyCatalogLoader())->loadFromFile($path);

        self::assertSame(7, $catalog->migrationPressureLookbackDays());
        self::assertSame(60, $catalog->migrationPressureCommitThreshold());
        self::assertSame(5, $catalog->migrationPressureMoveCooldownDays());
        self::assertSame(3, $catalog->migrationPressureDailyMoveCap());
        self::assertSame(12, $catalog->migrationPressureMaxTravelDistance());
        self::assertSame(30, $catalog->migrationPressureWeightProsperityGap());
        self::assertSame(20, $catalog->migrationPressureWeightTreasuryGap());
        self::assertSame(15, $catalog->migrationPressureWeightCrowdingGap());
    }

    public function testRejectsInvalidMigrationPressureConfig(): void
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
migration_pressure:
  lookback_days: 0
  commit_threshold: 60
  move_cooldown_days: 5
  daily_move_cap: 3
  max_travel_distance: 12
  weights:
    prosperity_gap: 30
    treasury_gap: 20
    crowding_gap: 15
YAML;

        $path = $this->writeTempYaml($yaml);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('migration_pressure.lookback_days must be an integer >= 1.');

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
