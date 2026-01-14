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

            $existing = $this->entityManager->getRepository(TechniqueDefinition::class)->findOneBy(['code' => $code]);
            if ($existing instanceof TechniqueDefinition) {
                $existing->setName($name);
                $existing->setType($type);
                /** @var array<string,mixed> $config */
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
}

