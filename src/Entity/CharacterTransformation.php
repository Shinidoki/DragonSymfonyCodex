<?php

namespace App\Entity;

use App\Game\Domain\Transformations\Transformation;
use App\Repository\CharacterTransformationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterTransformationRepository::class)]
#[ORM\Table(name: 'character_transformation')]
#[ORM\UniqueConstraint(name: 'uniq_character_transformation_character_transformation', columns: ['character_id', 'transformation'])]
class CharacterTransformation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(enumType: Transformation::class)]
    private Transformation $transformation;

    #[ORM\Column(options: ['default' => 0])]
    private int $proficiency = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Character $character, Transformation $transformation, int $proficiency = 0)
    {
        if ($proficiency < 0 || $proficiency > 100) {
            throw new \InvalidArgumentException('proficiency must be in range 0..100.');
        }

        $this->character      = $character;
        $this->transformation = $transformation;
        $this->proficiency    = $proficiency;
        $this->createdAt      = new \DateTimeImmutable();
        $this->updatedAt      = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCharacter(): Character
    {
        return $this->character;
    }

    public function getTransformation(): Transformation
    {
        return $this->transformation;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

