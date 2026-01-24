<?php

namespace App\Game\Domain\Techniques\Prepared;

final readonly class PreparedTechniqueState
{
    public string                 $techniqueCode;
    public PreparedTechniquePhase $phase;
    public int                    $ticksRemaining;
    public int                    $sinceTick;

    public function __construct(string $techniqueCode, PreparedTechniquePhase $phase, int $ticksRemaining, int $sinceTick)
    {
        $techniqueCode = strtolower(trim($techniqueCode));
        if ($techniqueCode === '') {
            throw new \InvalidArgumentException('techniqueCode must not be empty.');
        }
        if ($ticksRemaining < 0) {
            throw new \InvalidArgumentException('ticksRemaining must be >= 0.');
        }
        if ($sinceTick < 0) {
            throw new \InvalidArgumentException('sinceTick must be >= 0.');
        }

        $this->techniqueCode  = $techniqueCode;
        $this->phase          = $phase;
        $this->ticksRemaining = $ticksRemaining;
        $this->sinceTick      = $sinceTick;
    }
}

