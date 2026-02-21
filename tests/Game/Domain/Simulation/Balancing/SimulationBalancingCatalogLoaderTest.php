<?php

declare(strict_types=1);

namespace App\Tests\Game\Domain\Simulation\Balancing;

use App\Game\Domain\Simulation\Balancing\SimulationBalancingCatalogLoader;
use PHPUnit\Framework\TestCase;

final class SimulationBalancingCatalogLoaderTest extends TestCase
{
    public function testLoadsProfilesAndMetricBounds(): void
    {
        $yaml = <<<'YAML'
profiles:
  default:
    unemployment_rate:
      max: 0.35
    settlements_active:
      min: 2
  stress:
    unemployment_rate:
      max: 0.45
    migration_commits:
      min: 1
      max: 25
YAML;

        $path = $this->writeTempYaml($yaml);
        $catalog = (new SimulationBalancingCatalogLoader())->loadFromFile($path);

        self::assertSame(0.35, $catalog->max('default', 'unemployment_rate'));
        self::assertSame(2.0, $catalog->min('default', 'settlements_active'));
        self::assertSame(1.0, $catalog->min('stress', 'migration_commits'));
        self::assertSame(25.0, $catalog->max('stress', 'migration_commits'));
    }

    public function testRejectsMissingProfiles(): void
    {
        $path = $this->writeTempYaml("profiles: {}\n");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('simulation_balancing.profiles must define at least one profile.');
        (new SimulationBalancingCatalogLoader())->loadFromFile($path);
    }

    public function testRejectsMinGreaterThanMax(): void
    {
        $yaml = <<<'YAML'
profiles:
  default:
    unemployment_rate:
      min: 0.6
      max: 0.3
YAML;
        $path = $this->writeTempYaml($yaml);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('profiles.default.unemployment_rate min must be <= max.');
        (new SimulationBalancingCatalogLoader())->loadFromFile($path);
    }

    private function writeTempYaml(string $yaml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sim_balance_');
        if ($path === false) {
            self::fail('Failed to create temp file.');
        }

        file_put_contents($path, $yaml);

        return $path;
    }
}
