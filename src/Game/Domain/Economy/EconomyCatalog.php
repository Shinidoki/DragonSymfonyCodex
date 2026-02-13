<?php

namespace App\Game\Domain\Economy;

/**
 * @phpstan-type JobDef array{label:string,wage_weight:int,work_radius:int}
 * @phpstan-type EmploymentPoolItem array{code:string,weight:int}
 * @phpstan-type SettlementProductionDef array{per_work_unit_base:int,per_work_unit_prosperity_mult:int,randomness_pct:float}
 * @phpstan-type SettlementDef array{wage_pool_rate:float,tax_rate:float,production:SettlementProductionDef}
 * @phpstan-type ThresholdsDef array{money_low_employed:int,money_low_unemployed:int}
 * @phpstan-type TournamentRadiusDef array{base:int,per_spend:int,max:int}
 * @phpstan-type TournamentGainsDef array{fame_base:int,fame_per_spend:int,prosperity_base:int,prosperity_per_spend:int,per_participant_fame:int}
 * @phpstan-type TournamentFeedbackDef array{lookback_days:int,sample_size_min:int,spend_multiplier_step:float,radius_delta_step:int,spend_multiplier_min:float,spend_multiplier_max:float,radius_delta_min:int,radius_delta_max:int}
 * @phpstan-type TournamentsDef array{min_spend:int,max_spend_fraction_of_treasury:float,prize_pool_fraction:float,duration_days:int,radius:TournamentRadiusDef,gains:TournamentGainsDef,tournament_feedback:TournamentFeedbackDef}
 * @phpstan-type TournamentInterestWeightsDef array{distance:int,prize_pool:int,archetype_bias:int,money_pressure:int,cooldown_penalty:int}
 * @phpstan-type TournamentInterestDef array{commit_threshold:int,weights:TournamentInterestWeightsDef}
 */
final readonly class EconomyCatalog
{
    /**
     * @param array<string,JobDef>                   $jobs
     * @param array<string,list<EmploymentPoolItem>> $employmentPools
     * @param SettlementDef                          $settlement
     * @param ThresholdsDef                          $thresholds
     */
    public function __construct(
        private array $jobs,
        private array $employmentPools,
        private array $settlement,
        private array $thresholds,
        private array $tournaments = [],
        private array $tournamentInterest = [],
    )
    {
    }

    /**
     * @return array<string,JobDef>
     */
    public function jobs(): array
    {
        return $this->jobs;
    }

    /**
     * @return array<string,list<EmploymentPoolItem>>
     */
    public function employmentPools(): array
    {
        return $this->employmentPools;
    }

    /**
     * @return SettlementDef
     */
    public function settlement(): array
    {
        return $this->settlement;
    }

    /**
     * @return ThresholdsDef
     */
    public function thresholds(): array
    {
        return $this->thresholds;
    }

    /**
     * @return TournamentsDef
     */
    public function tournaments(): array
    {
        /** @var TournamentsDef $t */
        $t = $this->tournaments;

        return $t;
    }

    public function jobWageWeight(string $jobCode): int
    {
        $def = $this->jobs[$jobCode] ?? null;
        if (!is_array($def)) {
            throw new \InvalidArgumentException(sprintf('Unknown job: %s', $jobCode));
        }

        return (int)$def['wage_weight'];
    }

    public function jobWorkRadius(string $jobCode): int
    {
        $def = $this->jobs[$jobCode] ?? null;
        if (!is_array($def)) {
            throw new \InvalidArgumentException(sprintf('Unknown job: %s', $jobCode));
        }

        return (int)$def['work_radius'];
    }

    public function settlementWagePoolRate(): float
    {
        return (float)$this->settlement['wage_pool_rate'];
    }

    public function settlementTaxRate(): float
    {
        return (float)$this->settlement['tax_rate'];
    }

    public function settlementPerWorkUnitBase(): int
    {
        return (int)$this->settlement['production']['per_work_unit_base'];
    }

    public function settlementPerWorkUnitProsperityMult(): int
    {
        return (int)$this->settlement['production']['per_work_unit_prosperity_mult'];
    }

    public function settlementRandomnessPct(): float
    {
        return (float)$this->settlement['production']['randomness_pct'];
    }

    public function moneyLowThresholdEmployed(): int
    {
        return (int)$this->thresholds['money_low_employed'];
    }

    public function moneyLowThresholdUnemployed(): int
    {
        return (int)$this->thresholds['money_low_unemployed'];
    }

    public function tournamentMinSpend(): int
    {
        return (int)($this->tournaments['min_spend'] ?? 0);
    }

    public function tournamentMaxSpendFractionOfTreasury(): float
    {
        return (float)($this->tournaments['max_spend_fraction_of_treasury'] ?? 0.0);
    }

    public function tournamentPrizePoolFraction(): float
    {
        return (float)($this->tournaments['prize_pool_fraction'] ?? 0.0);
    }

    public function tournamentDurationDays(): int
    {
        return (int)($this->tournaments['duration_days'] ?? 0);
    }

    public function tournamentRadiusBase(): int
    {
        return (int)($this->tournaments['radius']['base'] ?? 0);
    }

    public function tournamentRadiusPerSpend(): int
    {
        return (int)($this->tournaments['radius']['per_spend'] ?? 1);
    }

    public function tournamentRadiusMax(): int
    {
        return (int)($this->tournaments['radius']['max'] ?? 0);
    }

    public function tournamentFameBase(): int
    {
        return (int)($this->tournaments['gains']['fame_base'] ?? 0);
    }

    public function tournamentFamePerSpend(): int
    {
        return (int)($this->tournaments['gains']['fame_per_spend'] ?? 0);
    }

    public function tournamentProsperityBase(): int
    {
        return (int)($this->tournaments['gains']['prosperity_base'] ?? 0);
    }

    public function tournamentProsperityPerSpend(): int
    {
        return (int)($this->tournaments['gains']['prosperity_per_spend'] ?? 0);
    }

    public function tournamentPerParticipantFame(): int
    {
        return (int)($this->tournaments['gains']['per_participant_fame'] ?? 0);
    }

    public function tournamentFeedbackLookbackDays(): int
    {
        return (int)($this->tournaments['tournament_feedback']['lookback_days'] ?? 14);
    }

    public function tournamentFeedbackSampleSizeMin(): int
    {
        return (int)($this->tournaments['tournament_feedback']['sample_size_min'] ?? 2);
    }

    public function tournamentFeedbackSpendMultiplierStep(): float
    {
        return (float)($this->tournaments['tournament_feedback']['spend_multiplier_step'] ?? 0.1);
    }

    public function tournamentFeedbackRadiusDeltaStep(): int
    {
        return (int)($this->tournaments['tournament_feedback']['radius_delta_step'] ?? 1);
    }

    public function tournamentFeedbackSpendMultiplierMin(): float
    {
        return (float)($this->tournaments['tournament_feedback']['spend_multiplier_min'] ?? 0.7);
    }

    public function tournamentFeedbackSpendMultiplierMax(): float
    {
        return (float)($this->tournaments['tournament_feedback']['spend_multiplier_max'] ?? 1.3);
    }

    public function tournamentFeedbackRadiusDeltaMin(): int
    {
        return (int)($this->tournaments['tournament_feedback']['radius_delta_min'] ?? -3);
    }

    public function tournamentFeedbackRadiusDeltaMax(): int
    {
        return (int)($this->tournaments['tournament_feedback']['radius_delta_max'] ?? 3);
    }

    public function tournamentInterestCommitThreshold(): int
    {
        return (int)($this->tournamentInterest['commit_threshold'] ?? 60);
    }

    public function tournamentInterestWeightDistance(): int
    {
        return (int)($this->tournamentInterest['weights']['distance'] ?? 30);
    }

    public function tournamentInterestWeightPrizePool(): int
    {
        return (int)($this->tournamentInterest['weights']['prize_pool'] ?? 25);
    }

    public function tournamentInterestWeightArchetypeBias(): int
    {
        return (int)($this->tournamentInterest['weights']['archetype_bias'] ?? 20);
    }

    public function tournamentInterestWeightMoneyPressure(): int
    {
        return (int)($this->tournamentInterest['weights']['money_pressure'] ?? 15);
    }

    public function tournamentInterestWeightCooldownPenalty(): int
    {
        return (int)($this->tournamentInterest['weights']['cooldown_penalty'] ?? 20);
    }

    /**
     * Deterministically pick a job code for initial world generation.
     */
    public function pickJobForArchetype(string $worldSeed, int $index, string $npcArchetype): ?string
    {
        $worldSeed = trim($worldSeed);
        if ($worldSeed === '') {
            throw new \InvalidArgumentException('worldSeed must not be empty.');
        }
        if ($index <= 0) {
            throw new \InvalidArgumentException('index must be a positive integer.');
        }
        $npcArchetype = strtolower(trim($npcArchetype));
        if ($npcArchetype === '') {
            throw new \InvalidArgumentException('npcArchetype must not be empty.');
        }

        $pool = $this->employmentPools[$npcArchetype] ?? null;
        if (!is_array($pool) || $pool === []) {
            return null;
        }

        $total = 0;
        foreach ($pool as $item) {
            $total += $item['weight'];
        }
        if ($total <= 0) {
            return null;
        }

        $roll = ($this->hashInt(sprintf('%s:job:%s:%d', $worldSeed, $npcArchetype, $index)) % $total) + 1;
        foreach ($pool as $item) {
            $roll -= $item['weight'];
            if ($roll <= 0) {
                return $item['code'];
            }
        }

        return null;
    }

    public function pickJobForArchetypeRandom(string $npcArchetype): ?string
    {
        $npcArchetype = strtolower(trim($npcArchetype));
        if ($npcArchetype === '') {
            throw new \InvalidArgumentException('npcArchetype must not be empty.');
        }

        $pool = $this->employmentPools[$npcArchetype] ?? null;
        if (!is_array($pool) || $pool === []) {
            return null;
        }

        $total = 0;
        foreach ($pool as $item) {
            $total += $item['weight'];
        }
        if ($total <= 0) {
            return null;
        }

        $roll = random_int(1, $total);
        foreach ($pool as $item) {
            $roll -= $item['weight'];
            if ($roll <= 0) {
                return $item['code'];
            }
        }

        return null;
    }

    private function hashInt(string $input): int
    {
        $hash = hash('sha256', $input);

        return (int)hexdec(substr($hash, 0, 8));
    }
}
