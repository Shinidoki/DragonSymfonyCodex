<?php

namespace App\Game\Application\Techniques;

use App\Entity\TechniqueDefinition;
use App\Game\Domain\Techniques\TechniqueType;
use Doctrine\ORM\EntityManagerInterface;

final class TechniqueImportService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function importFromJsonString(string $json): TechniqueImportResult
    {
        /** @var mixed $decoded */
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (is_array($decoded) && array_is_list($decoded)) {
            return $this->importMany($decoded);
        }

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Technique JSON must decode into an object or list of objects.');
        }

        return $this->importMany([$decoded]);
    }

    /**
     * @param list<mixed> $items
     */
    private function importMany(array $items): TechniqueImportResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Each technique entry must be an object.');
            }

            $code    = strtolower(trim((string)($item['code'] ?? '')));
            $name    = trim((string)($item['name'] ?? ''));
            $typeRaw = strtolower(trim((string)($item['type'] ?? '')));
            $enabled = (bool)($item['enabled'] ?? true);
            $version = (int)($item['version'] ?? 1);
            $config  = $item['config'] ?? [];

            if ($code === '' || $name === '' || $typeRaw === '') {
                throw new \InvalidArgumentException('Technique entry requires code, name, and type.');
            }

            try {
                $type = TechniqueType::from($typeRaw);
            } catch (\ValueError) {
                throw new \InvalidArgumentException(sprintf('Unknown technique type: %s', $typeRaw));
            }

            if (!is_array($config)) {
                throw new \InvalidArgumentException('Technique config must be an object.');
            }

            /** @var array<string,mixed> $config */
            $this->validateConfig($type, $config);

            $existing = $this->entityManager->getRepository(TechniqueDefinition::class)->findOneBy(['code' => $code]);
            if ($existing instanceof TechniqueDefinition) {
                $existing->setName($name);
                $existing->setType($type);
                $existing->setConfig($config);
                $existing->setEnabled($enabled);
                $existing->setVersion($version);
                $updated++;
                continue;
            }

            /** @var array<string,mixed> $config */
            $definition = new TechniqueDefinition(
                code: $code,
                name: $name,
                type: $type,
                config: $config,
                enabled: $enabled,
                version: $version,
            );

            $this->entityManager->persist($definition);
            $created++;
        }

        $this->entityManager->flush();

        return new TechniqueImportResult($created, $updated, $skipped);
    }

    /**
     * @param array<string,mixed> $config
     */
    private function validateConfig(TechniqueType $type, array $config): void
    {
        $aimModes = $config['aimModes'] ?? null;
        if (!is_array($aimModes) || $aimModes === []) {
            throw new \InvalidArgumentException('Technique config.aimModes must be a non-empty list.');
        }

        $allowedAimModes = ['self', 'actor', 'dir', 'point'];
        foreach ($aimModes as $mode) {
            if (!is_string($mode) || !in_array($mode, $allowedAimModes, true)) {
                throw new \InvalidArgumentException(sprintf('Invalid aimMode: %s', is_scalar($mode) ? (string)$mode : gettype($mode)));
            }
        }

        $delivery        = $config['delivery'] ?? null;
        $allowedDelivery = ['point', 'projectile', 'ray', 'aoe'];
        if (!is_string($delivery) || !in_array($delivery, $allowedDelivery, true)) {
            throw new \InvalidArgumentException('Technique config.delivery must be one of: point, projectile, ray, aoe.');
        }

        if (!isset($config['range']) || !is_int($config['range']) || $config['range'] < 0) {
            throw new \InvalidArgumentException('Technique config.range must be an int >= 0.');
        }

        if (!isset($config['kiCost']) || !is_int($config['kiCost']) || $config['kiCost'] < 0) {
            throw new \InvalidArgumentException('Technique config.kiCost must be an int >= 0.');
        }

        if ($delivery === 'aoe') {
            if (!isset($config['aoeRadius']) || !is_int($config['aoeRadius']) || $config['aoeRadius'] < 0) {
                throw new \InvalidArgumentException('Technique config.aoeRadius must be an int >= 0 when delivery=aoe.');
            }
        }

        if ($delivery === 'ray') {
            $piercing = $config['piercing'] ?? null;
            if (!is_string($piercing) || !in_array($piercing, ['first', 'all'], true)) {
                throw new \InvalidArgumentException('Technique config.piercing must be one of: first, all when delivery=ray.');
            }
        }

        if ($type === TechniqueType::Charged) {
            if (!isset($config['chargeTicks']) || !is_int($config['chargeTicks']) || $config['chargeTicks'] < 0) {
                throw new \InvalidArgumentException('Technique config.chargeTicks must be an int >= 0 when type=charged.');
            }

            if (isset($config['holdKiPerTick']) && (!is_int($config['holdKiPerTick']) || $config['holdKiPerTick'] < 0)) {
                throw new \InvalidArgumentException('Technique config.holdKiPerTick must be an int >= 0 when provided.');
            }

            if (isset($config['allowMoveWhilePrepared']) && !is_bool($config['allowMoveWhilePrepared'])) {
                throw new \InvalidArgumentException('Technique config.allowMoveWhilePrepared must be boolean when provided.');
            }
        }
    }
}
