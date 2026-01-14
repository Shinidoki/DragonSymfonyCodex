<?php

namespace App\Entity;

use App\Repository\CharacterTechniqueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterTechniqueRepository::class)]
#[ORM\Table(name: 'character_technique')]
#[ORM\UniqueConstraint(name: 'uniq_character_technique_character_technique', columns: ['character_id', 'technique_id'])]
class CharacterTechnique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\ManyToOne(targetEntity: TechniqueDefinition::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TechniqueDefinition $technique;

    #[ORM\Column(options: ['default' => 0])]
    private int $proficiency = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Character $character, TechniqueDefinition $technique, int $proficiency = 0)
    {
        if ($proficiency < 0 || $proficiency > 100) {
            throw new \InvalidArgumentException('proficiency must be in range 0..100.');
        }

        $this->character   = $character;
        $this->technique   = $technique;
        $this->proficiency = $proficiency;
        $this->createdAt   = new \DateTimeImmutable();
        $this->updatedAt   = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCharacter(): Character
    {
        return $this->character;
    }

    public function getTechnique(): TechniqueDefinition
    {
        return $this->technique;
    }

    public function getProficiency(): int
    {
        return $this->proficiency;
    }

    public function setProficiency(int $proficiency): void
    {
        if ($proficiency < 0 || $proficiency > 100) {
            throw new \InvalidArgumentException('proficiency must be in range 0..100.');
        }

        $this->proficiency = $proficiency;
        $this->updatedAt   = new \DateTimeImmutable();
    }

    public function incrementProficiency(int $by = 1): void
    {
        if ($by < 0) {
            throw new \InvalidArgumentException('by must be >= 0.');
        }

        $this->setProficiency(min(100, $this->proficiency + $by));
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

