<?php

namespace App\Twig;

use App\Game\Domain\Stats\StatTier;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class StatTierExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('stat_tier_label', $this->statTierLabel(...)),
        ];
    }

    public function statTierLabel(int $value): string
    {
        return StatTier::fromValue($value)->label();
    }
}

