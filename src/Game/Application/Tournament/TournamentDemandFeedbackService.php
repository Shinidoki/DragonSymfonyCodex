<?php

declare(strict_types=1);

namespace App\Game\Application\Tournament;

use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\World;
use App\Game\Application\Economy\EconomyCatalogProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class TournamentDemandFeedbackService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EconomyCatalogProviderInterface $economyCatalogProvider,
    ) {
    }

    /**
     * @return array{spendMultiplier:float,radiusDelta:int,sampleSize:int}
     */
    public function forSettlement(World $world, Settlement $settlement, int $worldDay): array
    {
        if ($worldDay < 0) {
            throw new \InvalidArgumentException('worldDay must be >= 0.');
        }

        $catalog = $this->economyCatalogProvider->get();
        $lookback = $catalog->tournamentFeedbackLookbackDays();
        $minDay = max(0, $worldDay - $lookback + 1);

        /** @var list<CharacterEvent> $events */
        $events = $this->entityManager
            ->getRepository(CharacterEvent::class)
            ->findBy(['world' => $world, 'type' => ['tournament_resolved', 'tournament_canceled']], ['day' => 'DESC']);

        $resolved = 0;
        $canceled = 0;
        $sx = $settlement->getX();
        $sy = $settlement->getY();

        foreach ($events as $event) {
            if ($event->getDay() < $minDay || $event->getDay() > $worldDay) {
                continue;
            }

            $data = $event->getData();
            if (!is_array($data)) {
                continue;
            }

            $x = $data['center_x'] ?? null;
            $y = $data['center_y'] ?? null;
            if (!is_int($x) || !is_int($y) || $x !== $sx || $y !== $sy) {
                continue;
            }

            if ($event->getType() === 'tournament_resolved') {
                $resolved++;
            }
            if ($event->getType() === 'tournament_canceled') {
                $canceled++;
            }
        }

        $sampleSize = $resolved + $canceled;
        if ($sampleSize < $catalog->tournamentFeedbackSampleSizeMin()) {
            return [
                'spendMultiplier' => 1.0,
                'radiusDelta' => 0,
                'sampleSize' => $sampleSize,
            ];
        }

        $score = $resolved - $canceled;
        $spendMultiplier = 1 + ($score * $catalog->tournamentFeedbackSpendMultiplierStep());
        $spendMultiplier = max($catalog->tournamentFeedbackSpendMultiplierMin(), min($catalog->tournamentFeedbackSpendMultiplierMax(), $spendMultiplier));

        $radiusDelta = $score * $catalog->tournamentFeedbackRadiusDeltaStep();
        $radiusDelta = max($catalog->tournamentFeedbackRadiusDeltaMin(), min($catalog->tournamentFeedbackRadiusDeltaMax(), $radiusDelta));

        return [
            'spendMultiplier' => round($spendMultiplier, 2),
            'radiusDelta' => $radiusDelta,
            'sampleSize' => $sampleSize,
        ];
    }
}
