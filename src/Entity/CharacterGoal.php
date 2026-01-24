<?php

namespace App\Entity;

use App\Repository\CharacterGoalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterGoalRepository::class)]
#[ORM\Table(name: 'game_character_goal')]
#[ORM\UniqueConstraint(name: 'uniq_character_goal_character', columns: ['character_id'])]
class CharacterGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $lifeGoalCode = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $currentGoalCode = null;

    /**
     * @var array<string,mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $currentGoalData = null;

    #[ORM\Column(options: ['default' => 0])]
    private bool $currentGoalComplete = false;

    #[ORM\Column(options: ['default' => -1])]
    private int $lastResolvedDay = -1;

    #[ORM\Column(options: ['default' => 0])]
    private int $lastProcessedEventId = 0;

    public function __construct(Character $character)
    {
        $this->character = $character;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCharacter(): Character
    {
        return $this->character;
    }

    public function getLifeGoalCode(): ?string
    {
        return $this->lifeGoalCode;
    }

    public function setLifeGoalCode(?string $lifeGoalCode): void
    {
        if ($lifeGoalCode !== null && trim($lifeGoalCode) === '') {
            throw new \InvalidArgumentException('lifeGoalCode must not be empty when provided.');
        }

        $this->lifeGoalCode = $lifeGoalCode;
    }

    public function getCurrentGoalCode(): ?string
    {
        return $this->currentGoalCode;
    }

    public function setCurrentGoalCode(?string $currentGoalCode): void
    {
        if ($currentGoalCode !== null && trim($currentGoalCode) === '') {
            throw new \InvalidArgumentException('currentGoalCode must not be empty when provided.');
        }

        $this->currentGoalCode = $currentGoalCode;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getCurrentGoalData(): ?array
    {
        return $this->currentGoalData;
    }

    /**
     * @param array<string,mixed>|null $currentGoalData
     */
    public function setCurrentGoalData(?array $currentGoalData): void
    {
        $this->currentGoalData = $currentGoalData;
    }

    public function isCurrentGoalComplete(): bool
    {
        return $this->currentGoalComplete;
    }

    public function setCurrentGoalComplete(bool $currentGoalComplete): void
    {
        $this->currentGoalComplete = $currentGoalComplete;
    }

    public function getLastResolvedDay(): int
    {
        return $this->lastResolvedDay;
    }

    public function setLastResolvedDay(int $lastResolvedDay): void
    {
        if ($lastResolvedDay < -1) {
            throw new \InvalidArgumentException('lastResolvedDay must be >= -1.');
        }

        $this->lastResolvedDay = $lastResolvedDay;
    }

    public function getLastProcessedEventId(): int
    {
        return $this->lastProcessedEventId;
    }

    public function setLastProcessedEventId(int $lastProcessedEventId): void
    {
        if ($lastProcessedEventId < 0) {
            throw new \InvalidArgumentException('lastProcessedEventId must be >= 0.');
        }

        $this->lastProcessedEventId = $lastProcessedEventId;
    }
}
