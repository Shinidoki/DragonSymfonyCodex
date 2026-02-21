<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SimulationDailyKpiRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SimulationDailyKpiRepository::class)]
#[ORM\Table(name: 'simulation_daily_kpi')]
#[ORM\UniqueConstraint(name: 'uniq_sim_daily_kpi_world_day', columns: ['world_id', 'day'])]
#[ORM\Index(name: 'idx_sim_daily_kpi_world_day', columns: ['world_id', 'day'])]
class SimulationDailyKpi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: World::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private World $world;

    #[ORM\Column]
    private int $day;

    #[ORM\Column]
    private int $settlementsActive;

    #[ORM\Column]
    private int $populationTotal;

    #[ORM\Column]
    private int $unemployedCount;

    #[ORM\Column(type: Types::FLOAT)]
    private float $unemploymentRate;

    #[ORM\Column]
    private int $migrationCommits;

    #[ORM\Column]
    private int $tournamentAnnounced;

    #[ORM\Column]
    private int $tournamentResolved;

    #[ORM\Column]
    private int $tournamentCanceled;

    #[ORM\Column(type: Types::FLOAT)]
    private float $meanSettlementProsperity;

    #[ORM\Column(type: Types::FLOAT)]
    private float $meanSettlementTreasury;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        World $world,
        int $day,
        int $settlementsActive,
        int $populationTotal,
        int $unemployedCount,
        float $unemploymentRate,
        int $migrationCommits,
        int $tournamentAnnounced,
        int $tournamentResolved,
        int $tournamentCanceled,
        float $meanSettlementProsperity,
        float $meanSettlementTreasury,
    ) {
        if ($day < 0) {
            throw new \InvalidArgumentException('day must be >= 0.');
        }

        $this->world = $world;
        $this->day = $day;
        $this->settlementsActive = $settlementsActive;
        $this->populationTotal = $populationTotal;
        $this->unemployedCount = $unemployedCount;
        $this->unemploymentRate = $unemploymentRate;
        $this->migrationCommits = $migrationCommits;
        $this->tournamentAnnounced = $tournamentAnnounced;
        $this->tournamentResolved = $tournamentResolved;
        $this->tournamentCanceled = $tournamentCanceled;
        $this->meanSettlementProsperity = $meanSettlementProsperity;
        $this->meanSettlementTreasury = $meanSettlementTreasury;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorld(): World { return $this->world; }
    public function getDay(): int { return $this->day; }
    public function getSettlementsActive(): int { return $this->settlementsActive; }
    public function getPopulationTotal(): int { return $this->populationTotal; }
    public function getUnemployedCount(): int { return $this->unemployedCount; }
    public function getUnemploymentRate(): float { return $this->unemploymentRate; }
    public function getMigrationCommits(): int { return $this->migrationCommits; }
    public function getTournamentAnnounced(): int { return $this->tournamentAnnounced; }
    public function getTournamentResolved(): int { return $this->tournamentResolved; }
    public function getTournamentCanceled(): int { return $this->tournamentCanceled; }
    public function getMeanSettlementProsperity(): float { return $this->meanSettlementProsperity; }
    public function getMeanSettlementTreasury(): float { return $this->meanSettlementTreasury; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
