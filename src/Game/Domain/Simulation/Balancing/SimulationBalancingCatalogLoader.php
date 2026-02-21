<?php

declare(strict_types=1);

namespace App\Game\Domain\Simulation\Balancing;

use Symfony\Component\Yaml\Yaml;

final class SimulationBalancingCatalogLoader
{
    public function loadFromFile(string $path): SimulationBalancingCatalog
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Simulation balancing YAML not found: %s', $path));
        }

        $raw = Yaml::parseFile($path);
        if (!is_array($raw)) {
            throw new \InvalidArgumentException('Simulation balancing YAML must contain a mapping at the root.');
        }

        $profilesRaw = $raw['profiles'] ?? null;
        if (!is_array($profilesRaw) || $profilesRaw === []) {
            throw new \InvalidArgumentException('simulation_balancing.profiles must define at least one profile.');
        }

        $profiles = [];
        foreach ($profilesRaw as $profile => $metricsRaw) {
            if (!is_string($profile) || trim($profile) === '') {
                throw new \InvalidArgumentException('profiles keys must be non-empty strings.');
            }
            if (!is_array($metricsRaw) || $metricsRaw === []) {
                throw new \InvalidArgumentException(sprintf('profiles.%s must define at least one metric.', $profile));
            }

            $metrics = [];
            foreach ($metricsRaw as $metric => $boundsRaw) {
                if (!is_string($metric) || trim($metric) === '') {
                    throw new \InvalidArgumentException(sprintf('profiles.%s metric keys must be non-empty strings.', $profile));
                }
                if (!is_array($boundsRaw)) {
                    throw new \InvalidArgumentException(sprintf('profiles.%s.%s must be a mapping.', $profile, $metric));
                }

                $minRaw = $boundsRaw['min'] ?? null;
                $maxRaw = $boundsRaw['max'] ?? null;
                if ($minRaw === null && $maxRaw === null) {
                    throw new \InvalidArgumentException(sprintf('profiles.%s.%s must define min and/or max.', $profile, $metric));
                }

                $min = null;
                $max = null;
                if ($minRaw !== null) {
                    if (!is_int($minRaw) && !is_float($minRaw)) {
                        throw new \InvalidArgumentException(sprintf('profiles.%s.%s.min must be numeric.', $profile, $metric));
                    }
                    $min = (float) $minRaw;
                }
                if ($maxRaw !== null) {
                    if (!is_int($maxRaw) && !is_float($maxRaw)) {
                        throw new \InvalidArgumentException(sprintf('profiles.%s.%s.max must be numeric.', $profile, $metric));
                    }
                    $max = (float) $maxRaw;
                }
                if ($min !== null && $max !== null && $min > $max) {
                    throw new \InvalidArgumentException(sprintf('profiles.%s.%s min must be <= max.', $profile, $metric));
                }

                $metricDef = [];
                if ($min !== null) {
                    $metricDef['min'] = $min;
                }
                if ($max !== null) {
                    $metricDef['max'] = $max;
                }
                $metrics[$metric] = $metricDef;
            }

            $profiles[$profile] = $metrics;
        }

        return new SimulationBalancingCatalog($profiles);
    }
}
