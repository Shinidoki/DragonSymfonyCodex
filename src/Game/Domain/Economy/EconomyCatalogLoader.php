<?php

namespace App\Game\Domain\Economy;

use Symfony\Component\Yaml\Yaml;

final class EconomyCatalogLoader
{
    public function loadFromFile(string $path): EconomyCatalog
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Economy YAML not found: %s', $path));
        }

        $raw = Yaml::parseFile($path);
        if (!is_array($raw)) {
            throw new \InvalidArgumentException('Economy YAML must contain a mapping at the root.');
        }

        $settlement = $raw['settlement'] ?? null;
        if (!is_array($settlement)) {
            throw new \InvalidArgumentException('Economy YAML must define settlement.');
        }

        $wagePoolRate = $settlement['wage_pool_rate'] ?? null;
        $taxRate      = $settlement['tax_rate'] ?? null;
        $production   = $settlement['production'] ?? null;

        if ((!is_float($wagePoolRate) && !is_int($wagePoolRate)) || $wagePoolRate < 0 || $wagePoolRate > 1) {
            throw new \InvalidArgumentException('settlement.wage_pool_rate must be a number between 0 and 1.');
        }
        if ((!is_float($taxRate) && !is_int($taxRate)) || $taxRate < 0 || $taxRate > 1) {
            throw new \InvalidArgumentException('settlement.tax_rate must be a number between 0 and 1.');
        }
        if (!is_array($production)) {
            throw new \InvalidArgumentException('settlement.production must be a mapping.');
        }

        $perWorkUnitBase = $production['per_work_unit_base'] ?? null;
        $perWorkUnitPros = $production['per_work_unit_prosperity_mult'] ?? null;
        $randomnessPct   = $production['randomness_pct'] ?? 0.0;

        if (!is_int($perWorkUnitBase) || $perWorkUnitBase < 0) {
            throw new \InvalidArgumentException('settlement.production.per_work_unit_base must be an integer >= 0.');
        }
        if (!is_int($perWorkUnitPros) || $perWorkUnitPros < 0) {
            throw new \InvalidArgumentException('settlement.production.per_work_unit_prosperity_mult must be an integer >= 0.');
        }
        if ((!is_float($randomnessPct) && !is_int($randomnessPct)) || $randomnessPct < 0 || $randomnessPct > 1) {
            throw new \InvalidArgumentException('settlement.production.randomness_pct must be a number between 0 and 1.');
        }

        $thresholds = $raw['thresholds'] ?? null;
        if (!is_array($thresholds)) {
            throw new \InvalidArgumentException('Economy YAML must define thresholds.');
        }
        $moneyLowEmployed   = $thresholds['money_low_employed'] ?? null;
        $moneyLowUnemployed = $thresholds['money_low_unemployed'] ?? null;
        if (!is_int($moneyLowEmployed) || $moneyLowEmployed < 0) {
            throw new \InvalidArgumentException('thresholds.money_low_employed must be an integer >= 0.');
        }
        if (!is_int($moneyLowUnemployed) || $moneyLowUnemployed < 0) {
            throw new \InvalidArgumentException('thresholds.money_low_unemployed must be an integer >= 0.');
        }

        $jobs = $raw['jobs'] ?? null;
        if (!is_array($jobs) || $jobs === []) {
            throw new \InvalidArgumentException('Economy YAML must define jobs.');
        }

        /** @var array<string,array{label:string,wage_weight:int,work_radius:int}> $jobDefs */
        $jobDefs = [];
        foreach ($jobs as $code => $def) {
            if (!is_string($code) || trim($code) === '') {
                throw new \InvalidArgumentException('jobs keys must be non-empty strings.');
            }
            if (!is_array($def)) {
                throw new \InvalidArgumentException(sprintf('job %s must be a mapping.', $code));
            }
            $label      = $def['label'] ?? null;
            $wageWeight = $def['wage_weight'] ?? null;
            $workRadius = $def['work_radius'] ?? null;

            if (!is_string($label) || trim($label) === '') {
                throw new \InvalidArgumentException(sprintf('job %s label must be a non-empty string.', $code));
            }
            if (!is_int($wageWeight) || $wageWeight <= 0) {
                throw new \InvalidArgumentException(sprintf('job %s wage_weight must be a positive integer.', $code));
            }
            if (!is_int($workRadius) || $workRadius < 0) {
                throw new \InvalidArgumentException(sprintf('job %s work_radius must be an integer >= 0.', $code));
            }

            $jobDefs[$code] = [
                'label'       => $label,
                'wage_weight' => $wageWeight,
                'work_radius' => $workRadius,
            ];
        }

        $employmentPoolsRaw = $raw['employment_pools'] ?? [];
        if ($employmentPoolsRaw === null) {
            $employmentPoolsRaw = [];
        }
        if (!is_array($employmentPoolsRaw)) {
            throw new \InvalidArgumentException('employment_pools must be a mapping when provided.');
        }

        /** @var array<string,list<array{code:string,weight:int}>> $employmentPools */
        $employmentPools = [];
        foreach ($employmentPoolsRaw as $archetype => $pool) {
            if (!is_string($archetype) || trim($archetype) === '') {
                throw new \InvalidArgumentException('employment_pools keys must be non-empty strings.');
            }
            if ($pool === null) {
                $pool = [];
            }
            if (!is_array($pool)) {
                throw new \InvalidArgumentException(sprintf('employment_pools.%s must be a list.', $archetype));
            }

            $items = [];
            foreach ($pool as $i => $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException(sprintf('employment_pools.%s[%d] must be a mapping.', $archetype, $i));
                }
                $code   = $item['code'] ?? null;
                $weight = $item['weight'] ?? null;
                if (!is_string($code) || trim($code) === '') {
                    throw new \InvalidArgumentException(sprintf('employment_pools.%s[%d].code must be a non-empty string.', $archetype, $i));
                }
                if (!array_key_exists($code, $jobDefs)) {
                    throw new \InvalidArgumentException(sprintf('employment_pools.%s[%d] references unknown job: %s', $archetype, $i, $code));
                }
                if (!is_int($weight) || $weight <= 0) {
                    throw new \InvalidArgumentException(sprintf('employment_pools.%s[%d].weight must be a positive integer.', $archetype, $i));
                }

                $items[] = ['code' => $code, 'weight' => $weight];
            }

            $employmentPools[$archetype] = $items;
        }

        $tournaments = $raw['tournaments'] ?? null;
        if (!is_array($tournaments)) {
            $tournaments = [
                'min_spend'                      => 0,
                'max_spend_fraction_of_treasury' => 0.0,
                'prize_pool_fraction'            => 0.0,
                'duration_days'                  => 0,
                'radius'                         => ['base' => 0, 'per_spend' => 1, 'max' => 0],
                'gains'                          => ['fame_base' => 0, 'fame_per_spend' => 0, 'prosperity_base' => 0, 'prosperity_per_spend' => 0, 'per_participant_fame' => 0],
            ];
        }

        $minSpend = $tournaments['min_spend'] ?? null;
        if (!is_int($minSpend) || $minSpend < 0) {
            throw new \InvalidArgumentException('tournaments.min_spend must be an integer >= 0.');
        }
        $maxFrac = $tournaments['max_spend_fraction_of_treasury'] ?? null;
        if ((!is_float($maxFrac) && !is_int($maxFrac)) || $maxFrac < 0 || $maxFrac > 1) {
            throw new \InvalidArgumentException('tournaments.max_spend_fraction_of_treasury must be a number between 0 and 1.');
        }
        $prizeFrac = $tournaments['prize_pool_fraction'] ?? null;
        if ((!is_float($prizeFrac) && !is_int($prizeFrac)) || $prizeFrac < 0 || $prizeFrac > 1) {
            throw new \InvalidArgumentException('tournaments.prize_pool_fraction must be a number between 0 and 1.');
        }
        $durationDays = $tournaments['duration_days'] ?? null;
        if (!is_int($durationDays) || $durationDays < 1) {
            throw new \InvalidArgumentException('tournaments.duration_days must be an integer >= 1.');
        }

        $radius = $tournaments['radius'] ?? null;
        if (!is_array($radius)) {
            throw new \InvalidArgumentException('tournaments.radius must be a mapping.');
        }
        $radiusBase     = $radius['base'] ?? null;
        $radiusPerSpend = $radius['per_spend'] ?? null;
        $radiusMax      = $radius['max'] ?? null;
        if (!is_int($radiusBase) || $radiusBase < 0) {
            throw new \InvalidArgumentException('tournaments.radius.base must be an integer >= 0.');
        }
        if (!is_int($radiusPerSpend) || $radiusPerSpend <= 0) {
            throw new \InvalidArgumentException('tournaments.radius.per_spend must be a positive integer.');
        }
        if (!is_int($radiusMax) || $radiusMax < $radiusBase) {
            throw new \InvalidArgumentException('tournaments.radius.max must be an integer >= radius.base.');
        }

        $gains = $tournaments['gains'] ?? null;
        if (!is_array($gains)) {
            throw new \InvalidArgumentException('tournaments.gains must be a mapping.');
        }
        $fameBase           = $gains['fame_base'] ?? null;
        $famePerSpend       = $gains['fame_per_spend'] ?? null;
        $prosBase           = $gains['prosperity_base'] ?? null;
        $prosPerSpend       = $gains['prosperity_per_spend'] ?? null;
        $perParticipantFame = $gains['per_participant_fame'] ?? 0;
        foreach ([
                     'tournaments.gains.fame_base'            => $fameBase,
                     'tournaments.gains.fame_per_spend'       => $famePerSpend,
                     'tournaments.gains.prosperity_base'      => $prosBase,
                     'tournaments.gains.prosperity_per_spend' => $prosPerSpend,
                     'tournaments.gains.per_participant_fame' => $perParticipantFame,
                 ] as $key => $val) {
            if (!is_int($val) || $val < 0) {
                throw new \InvalidArgumentException(sprintf('%s must be an integer >= 0.', $key));
            }
        }

        return new EconomyCatalog(
            jobs: $jobDefs,
            employmentPools: $employmentPools,
            settlement: [
                'wage_pool_rate' => (float)$wagePoolRate,
                'tax_rate'       => (float)$taxRate,
                'production'     => [
                    'per_work_unit_base'            => $perWorkUnitBase,
                    'per_work_unit_prosperity_mult' => $perWorkUnitPros,
                    'randomness_pct'                => (float)$randomnessPct,
                ],
            ],
            thresholds: [
                'money_low_employed'   => $moneyLowEmployed,
                'money_low_unemployed' => $moneyLowUnemployed,
            ],
            tournaments: [
                'min_spend'                      => $minSpend,
                'max_spend_fraction_of_treasury' => (float)$maxFrac,
                'prize_pool_fraction'            => (float)$prizeFrac,
                'duration_days'                  => $durationDays,
                'radius'                         => [
                    'base'      => $radiusBase,
                    'per_spend' => $radiusPerSpend,
                    'max'       => $radiusMax,
                ],
                'gains'                          => [
                    'fame_base'            => $fameBase,
                    'fame_per_spend'       => $famePerSpend,
                    'prosperity_base'      => $prosBase,
                    'prosperity_per_spend' => $prosPerSpend,
                    'per_participant_fame' => $perParticipantFame,
                ],
            ],
        );
    }
}
