<?php

namespace App\Entity;

use App\Repository\TournamentParticipantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TournamentParticipantRepository::class)]
#[ORM\Table(name: 'game_tournament_participant')]
#[ORM\UniqueConstraint(name: 'uniq_tournament_participant', columns: ['tournament_id', 'character_id'])]
#[ORM\Index(name: 'idx_tournament_participant_tournament', columns: ['tournament_id'])]
#[ORM\Index(name: 'idx_tournament_participant_character', columns: ['character_id'])]
class TournamentParticipant
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_ELIMINATED = 'eliminated';
    public const STATUS_WINNER     = 'winner';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tournament::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_REGISTERED;

    #[ORM\Column]
    private int $registeredDay;

    #[ORM\Column(nullable: true)]
    private ?int $eliminatedDay = null;

    #[ORM\Column(nullable: true)]
    private ?int $seed = null;

    #[ORM\Column(nullable: true)]
    private ?int $finalRank = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tournament $tournament, Character $character, int $registeredDay)
    {
        if ($registeredDay < 0) {
            throw new \InvalidArgumentException('registeredDay must be >= 0.');
        }

        $this->tournament    = $tournament;
        $this->character     = $character;
        $this->registeredDay = $registeredDay;
        $this->createdAt     = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getCharacter(): Character
    {
        return $this->character;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRegisteredDay(): int
    {
        return $this->registeredDay;
    }

    public function getEliminatedDay(): ?int
    {
        return $this->eliminatedDay;
    }

    public function getSeed(): ?int
    {
        return $this->seed;
    }

    public function getFinalRank(): ?int
    {
        return $this->finalRank;
    }

    public function markEliminated(int $day): void
    {
        if ($day < 0) {
            throw new \InvalidArgumentException('day must be >= 0.');
        }

        $this->status        = self::STATUS_ELIMINATED;
        $this->eliminatedDay = $day;
    }

    public function markWinner(int $rank): void
    {
        if ($rank <= 0) {
            throw new \InvalidArgumentException('rank must be positive.');
        }

        $this->status    = self::STATUS_WINNER;
        $this->finalRank = $rank;
    }

    public function setSeed(?int $seed): void
    {
        if ($seed !== null && $seed <= 0) {
            throw new \InvalidArgumentException('seed must be positive when provided.');
        }

        $this->seed = $seed;
    }

    public function setFinalRank(?int $finalRank): void
    {
        if ($finalRank !== null && $finalRank <= 0) {
            throw new \InvalidArgumentException('finalRank must be positive when provided.');
        }

        $this->finalRank = $finalRank;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

