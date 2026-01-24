<?php

namespace App\Game\Domain\Map;

final class MapGenerator
{
    public function biomeFor(string $seed, TileCoord $coord): Biome
    {
        $seed = $this->assertSeed($seed);

        $n = $this->hashInt(sprintf('%s:%d:%d', $seed, $coord->x, $coord->y));

        $biomes = Biome::cases();
        $index  = $n % count($biomes);

        return $biomes[$index];
    }

    public function hasSettlementFor(string $seed, TileCoord $coord, Biome $biome): bool
    {
        $seed = $this->assertSeed($seed);

        if ($coord->x === 0 && $coord->y === 0) {
            return true;
        }
        if ($biome === Biome::Ocean) {
            return false;
        }
        if ($biome === Biome::City) {
            return true;
        }

        $n = $this->hashInt(sprintf('%s:settlement:%d:%d', $seed, $coord->x, $coord->y));

        return ($n % 100) < 3;
    }

    public function hasDojoFor(string $seed, TileCoord $coord, bool $hasSettlement): bool
    {
        $seed = $this->assertSeed($seed);

        if ($coord->x === 0 && $coord->y === 0) {
            return true;
        }
        if (!$hasSettlement) {
            return false;
        }

        $n = $this->hashInt(sprintf('%s:dojo:%d:%d', $seed, $coord->x, $coord->y));

        return ($n % 100) < 20;
    }

    private function assertSeed(string $seed): string
    {
        $seed = trim($seed);
        if ($seed === '') {
            throw new \InvalidArgumentException('Seed must not be empty.');
        }

        return $seed;
    }

    private function hashInt(string $input): int
    {
        $hash = hash('sha256', $input);

        return (int)hexdec(substr($hash, 0, 8));
    }
}
