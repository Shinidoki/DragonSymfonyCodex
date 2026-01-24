<?php

namespace App\Game\Domain\Goal;

use App\Entity\Character;
use App\Entity\World;

final class GoalPlanner
{
    /**
     * @var array<string,CurrentGoalHandlerInterface>
     */
    private array $handlersByClass = [];

    /**
     * @param iterable<CurrentGoalHandlerInterface> $handlers
     */
    public function __construct(iterable $handlers = [])
    {
        foreach ($handlers as $handler) {
            $this->handlersByClass[$handler::class] = $handler;
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function step(
        Character   $character,
        World       $world,
        string      $currentGoalCode,
        array       $data,
        GoalContext $context,
        GoalCatalog $catalog,
    ): GoalStepResult
    {
        $handlerClass = $catalog->currentGoalHandler($currentGoalCode);
        if ($handlerClass === null) {
            throw new \RuntimeException(sprintf('No handler configured for current goal: %s', $currentGoalCode));
        }

        $handler = $this->handlersByClass[$handlerClass] ?? null;
        if (!$handler instanceof CurrentGoalHandlerInterface) {
            throw new \RuntimeException(sprintf('Handler service not found for current goal %s: %s', $currentGoalCode, $handlerClass));
        }

        return $handler->step($character, $world, $data, $context);
    }
}

