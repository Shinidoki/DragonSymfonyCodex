<?php

namespace App\Game\Domain\Settlement;

use Symfony\Component\Yaml\Yaml;

final class ProjectCatalogLoader
{
    public function loadFromFile(string $path): ProjectCatalog
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Project catalog file not found: %s', $path));
        }

        $parsed = Yaml::parseFile($path);
        if (!is_array($parsed)) {
            throw new \InvalidArgumentException('Project catalog must be a YAML map.');
        }

        $buildings = $parsed['buildings'] ?? null;
        if (!is_array($buildings)) {
            throw new \InvalidArgumentException('Project catalog must define buildings.');
        }

        $dojo = $buildings['dojo'] ?? null;
        if (!is_array($dojo)) {
            throw new \InvalidArgumentException('Project catalog must define buildings.dojo.');
        }

        $rules = $dojo['rules'] ?? [];
        if (!is_array($rules)) {
            $rules = [];
        }
        $dojoRules = $this->parseDojoRules($rules);

        $levels = $dojo['levels'] ?? null;
        if (!is_array($levels) || $levels === []) {
            throw new \InvalidArgumentException('Project catalog must define buildings.dojo.levels.');
        }

        $dojoLevels = [];
        foreach ($levels as $levelKey => $def) {
            if (is_int($levelKey)) {
                $level = $levelKey;
            } elseif (is_string($levelKey) && ctype_digit($levelKey)) {
                $level = (int)$levelKey;
            } else {
                continue;
            }

            if (!is_array($def)) {
                continue;
            }

            $dojoLevels[$level] = $this->parseDojoLevelDef($level, $def);
        }

        ksort($dojoLevels);

        if ($dojoLevels === []) {
            throw new \InvalidArgumentException('No valid dojo level definitions found.');
        }

        return new ProjectCatalog($dojoLevels, $dojoRules);
    }

    /**
     * @param array<string,mixed> $rules
     *
     * @return array{challenge_cooldown_days:int,training_fee_base:int,training_fee_per_level:int}
     */
    private function parseDojoRules(array $rules): array
    {
        $cooldown = $rules['challenge_cooldown_days'] ?? 7;
        $feeBase  = $rules['training_fee_base'] ?? 10;
        $feePer   = $rules['training_fee_per_level'] ?? 5;

        if (!is_int($cooldown) || $cooldown < 1) {
            throw new \InvalidArgumentException('Dojo rules.challenge_cooldown_days must be an int >= 1.');
        }
        if (!is_int($feeBase) || $feeBase < 0) {
            throw new \InvalidArgumentException('Dojo rules.training_fee_base must be an int >= 0.');
        }
        if (!is_int($feePer) || $feePer < 0) {
            throw new \InvalidArgumentException('Dojo rules.training_fee_per_level must be an int >= 0.');
        }

        return [
            'challenge_cooldown_days' => $cooldown,
            'training_fee_base'       => $feeBase,
            'training_fee_per_level'  => $feePer,
        ];
    }

    /**
     * @param array<string,mixed> $def
     *
     * @return array{materials_cost:int,base_required_work_units:int,target_duration_days:int,diversion_fraction:float,training_multiplier:float}
     */
    private function parseDojoLevelDef(int $level, array $def): array
    {
        if ($level <= 0) {
            throw new \InvalidArgumentException('Dojo level keys must be positive integers.');
        }

        $materials = $def['materials_cost'] ?? null;
        $baseReq   = $def['base_required_work_units'] ?? null;
        $days      = $def['target_duration_days'] ?? null;
        $divert    = $def['diversion_fraction'] ?? null;
        $mult      = $def['training_multiplier'] ?? null;

        if (!is_int($materials) || $materials < 0) {
            throw new \InvalidArgumentException(sprintf('Dojo level %d materials_cost must be an int >= 0.', $level));
        }
        if (!is_int($baseReq) || $baseReq <= 0) {
            throw new \InvalidArgumentException(sprintf('Dojo level %d base_required_work_units must be an int > 0.', $level));
        }
        if (!is_int($days) || $days <= 0) {
            throw new \InvalidArgumentException(sprintf('Dojo level %d target_duration_days must be an int > 0.', $level));
        }
        if (!is_float($divert) && !is_int($divert)) {
            throw new \InvalidArgumentException(sprintf('Dojo level %d diversion_fraction must be numeric.', $level));
        }
        $divert = (float)$divert;
        if ($divert <= 0.0 || $divert > 1.0) {
            throw new \InvalidArgumentException(sprintf('Dojo level %d diversion_fraction must be within (0,1].', $level));
        }
        if (!is_float($mult) && !is_int($mult)) {
            throw new \InvalidArgumentException(sprintf('Dojo level %d training_multiplier must be numeric.', $level));
        }
        $mult = (float)$mult;
        if ($mult <= 0.0) {
            throw new \InvalidArgumentException(sprintf('Dojo level %d training_multiplier must be > 0.', $level));
        }

        return [
            'materials_cost'           => $materials,
            'base_required_work_units' => $baseReq,
            'target_duration_days'     => $days,
            'diversion_fraction'       => $divert,
            'training_multiplier'      => $mult,
        ];
    }
}
