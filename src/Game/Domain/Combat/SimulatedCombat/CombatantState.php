<?php

namespace App\Game\Domain\Combat\SimulatedCombat;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\CharacterTransformation;
use App\Game\Domain\Techniques\Prepared\PreparedTechniquePhase;
use App\Game\Domain\Transformations\Transformation;
use App\Game\Domain\Transformations\TransformationState;

final class CombatantState
{
    public Character $character;
    public int       $teamId;

    /** @var list<CharacterTechnique> */
    public array $techniques;

    /** @var list<CharacterTransformation> */
    public array $transformations;

    public int $maxHp;
    public int $currentHp;

    public int $maxKi;
    public int $currentKi;

    public int $turnMeter = 0;

    public ?string                 $preparedTechniqueCode  = null;
    public ?PreparedTechniquePhase $preparedPhase          = null;
    public int                     $preparedTicksRemaining = 0;

    public TransformationState $transformationState;

    /**
     * @param list<CharacterTechnique>      $techniques
     * @param list<CharacterTransformation> $transformations
     */
    public function __construct(Character $character, int $teamId, array $techniques, array $transformations, int $maxHp, int $maxKi)
    {
        if ($teamId <= 0) {
            throw new \InvalidArgumentException('teamId must be positive.');
        }
        if ($maxHp <= 0) {
            throw new \InvalidArgumentException('maxHp must be positive.');
        }
        if ($maxKi <= 0) {
            throw new \InvalidArgumentException('maxKi must be positive.');
        }

        $this->character           = $character;
        $this->teamId              = $teamId;
        $this->techniques          = $techniques;
        $this->transformations     = $transformations;
        $this->maxHp               = $maxHp;
        $this->currentHp           = $maxHp;
        $this->maxKi               = $maxKi;
        $this->currentKi           = $maxKi;
        $this->transformationState = $character->getTransformationState();
    }

    public function isDefeated(): bool
    {
        return $this->currentHp <= 0;
    }

    public function hasPreparedTechnique(): bool
    {
        return $this->preparedTechniqueCode !== null;
    }

    public function clearPreparedTechnique(): void
    {
        $this->preparedTechniqueCode  = null;
        $this->preparedPhase          = null;
        $this->preparedTicksRemaining = 0;
    }

    public function startPreparingTechnique(string $techniqueCode, int $ticksRemaining): void
    {
        $techniqueCode = strtolower(trim($techniqueCode));
        if ($techniqueCode === '') {
            throw new \InvalidArgumentException('techniqueCode must not be empty.');
        }
        if ($ticksRemaining < 0) {
            throw new \InvalidArgumentException('ticksRemaining must be >= 0.');
        }

        $this->preparedTechniqueCode  = $techniqueCode;
        $this->preparedPhase          = PreparedTechniquePhase::Charging;
        $this->preparedTicksRemaining = $ticksRemaining;
    }

    public function decrementPreparedTick(): void
    {
        if ($this->preparedTicksRemaining <= 0) {
            return;
        }

        $this->preparedTicksRemaining--;
    }

    public function markPreparedReady(): void
    {
        if ($this->preparedTechniqueCode === null) {
            return;
        }

        $this->preparedPhase          = PreparedTechniquePhase::Ready;
        $this->preparedTicksRemaining = 0;
    }

    public function knowsTransformation(Transformation $t, int $minProficiency = 0): bool
    {
        foreach ($this->transformations as $k) {
            if ($k->getTransformation() === $t && $k->getProficiency() >= $minProficiency) {
                return true;
            }
        }

        return false;
    }
}
