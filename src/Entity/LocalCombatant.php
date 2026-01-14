<?php

namespace App\Entity;

use App\Repository\LocalCombatantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocalCombatantRepository::class)]
#[ORM\Table(name: 'local_combatant')]
#[ORM\UniqueConstraint(name: 'uniq_local_combatant_combat_actor', columns: ['combat_id', 'actor_id'])]
class LocalCombatant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LocalCombat::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LocalCombat $combat;

    #[ORM\Column]
    private int $actorId;

    #[ORM\Column]
    private int $maxHp;

    #[ORM\Column]
    private int $currentHp;

    #[ORM\Column]
    private int $maxKi;

    #[ORM\Column]
    private int $currentKi;

    #[ORM\Column(nullable: true)]
    private ?int $defeatedAtTick = null;

    public function __construct(LocalCombat $combat, int $actorId, int $maxHp, int $maxKi)
    {
        if ($actorId <= 0) {
            throw new \InvalidArgumentException('actorId must be positive.');
        }
        if ($maxHp <= 0) {
            throw new \InvalidArgumentException('maxHp must be positive.');
        }
        if ($maxKi <= 0) {
            throw new \InvalidArgumentException('maxKi must be positive.');
        }

        $this->combat    = $combat;
        $this->actorId   = $actorId;
        $this->maxHp     = $maxHp;
        $this->currentHp = $maxHp;
        $this->maxKi     = $maxKi;
        $this->currentKi = $maxKi;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCombat(): LocalCombat
    {
        return $this->combat;
    }

    public function getActorId(): int
    {
        return $this->actorId;
    }

    public function getMaxHp(): int
    {
        return $this->maxHp;
    }

    public function getCurrentHp(): int
    {
        return $this->currentHp;
    }

    public function getMaxKi(): int
    {
        return $this->maxKi;
    }

    public function getCurrentKi(): int
    {
        return $this->currentKi;
    }

    public function getDefeatedAtTick(): ?int
    {
        return $this->defeatedAtTick;
    }

    public function isDefeated(): bool
    {
        return $this->defeatedAtTick !== null || $this->currentHp <= 0;
    }

    public function applyDamage(int $amount, int $tick): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('damage must be positive.');
        }
        if ($tick < 0) {
            throw new \InvalidArgumentException('tick must be >= 0.');
        }
        if ($this->isDefeated()) {
            return;
        }

        $this->currentHp = max(0, $this->currentHp - $amount);
        if ($this->currentHp === 0) {
            $this->defeatedAtTick = $tick;
        }
    }

    public function spendKi(int $amount): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount must be positive.');
        }

        if ($this->currentKi < $amount) {
            return false;
        }

        $this->currentKi -= $amount;
        return true;
    }
}
