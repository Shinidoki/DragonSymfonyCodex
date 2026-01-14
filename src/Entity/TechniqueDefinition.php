<?php

namespace App\Entity;

use App\Game\Domain\Techniques\TechniqueType;
use App\Repository\TechniqueDefinitionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TechniqueDefinitionRepository::class)]
#[ORM\Table(name: 'technique_definition')]
#[ORM\UniqueConstraint(name: 'uniq_technique_definition_code', columns: ['code'])]
class TechniqueDefinition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $code;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(enumType: TechniqueType::class)]
    private TechniqueType $type;

    /**
     * @var array<string,mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $config;

    #[ORM\Column(options: ['default' => 1])]
    private bool $enabled = true;

    #[ORM\Column(options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(
        string $code,
        string $name,
        TechniqueType $type,
        array $config,
        bool $enabled = true,
        int $version = 1,
    )
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            throw new \InvalidArgumentException('code must not be empty.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('name must not be empty.');
        }

        if ($version <= 0) {
            throw new \InvalidArgumentException('version must be positive.');
        }

        $this->code      = $code;
        $this->name      = $name;
        $this->type      = $type;
        $this->config    = $config;
        $this->enabled   = $enabled;
        $this->version   = $version;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('name must not be empty.');
        }

        $this->name      = $name;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getType(): TechniqueType
    {
        return $this->type;
    }

    public function setType(TechniqueType $type): void
    {
        $this->type      = $type;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return array<string,mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string,mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config    = $config;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled   = $enabled;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        if ($version <= 0) {
            throw new \InvalidArgumentException('version must be positive.');
        }

        $this->version   = $version;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

