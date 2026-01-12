<?php

namespace App\Command;

use App\Entity\LocalActor;
use App\Entity\LocalCombat;
use App\Entity\LocalCombatant;
use App\Entity\LocalSession;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:local:list-actors',
    description: 'List actors in a local session (position, turn meter, and combat HP if present).',
)]
final class GameLocalListActorsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('session', null, InputOption::VALUE_REQUIRED, 'Local session id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionId = (int)$input->getOption('session');
        if ($sessionId <= 0) {
            $output->writeln('<error>--session must be a positive integer</error>');
            return Command::INVALID;
        }

        $session = $this->entityManager->find(LocalSession::class, $sessionId);
        if (!$session instanceof LocalSession) {
            $output->writeln('<error>Local session not found</error>');
            return Command::FAILURE;
        }

        /** @var list<LocalActor> $actors */
        $actors = $this->entityManager->getRepository(LocalActor::class)->findBy(['session' => $session], ['id' => 'ASC']);

        $combat      = $this->entityManager->getRepository(LocalCombat::class)->findOneBy(['session' => $session]);
        $hpByActorId = [];
        if ($combat instanceof LocalCombat) {
            /** @var list<LocalCombatant> $combatants */
            $combatants = $this->entityManager->getRepository(LocalCombatant::class)->findBy(['combat' => $combat], ['id' => 'ASC']);
            foreach ($combatants as $combatant) {
                $hpByActorId[$combatant->getActorId()] = sprintf('%d/%d', $combatant->getCurrentHp(), $combatant->getMaxHp());
            }
        }

        $output->writeln(sprintf('Local session #%d', (int)$session->getId()));

        foreach ($actors as $actor) {
            $id = (int)$actor->getId();
            $hp = $hpByActorId[$id] ?? null;

            $output->writeln(sprintf(
                'Actor #%d role=%s character=%d pos=(%d,%d) meter=%d%s',
                $id,
                $actor->getRole(),
                $actor->getCharacterId(),
                $actor->getX(),
                $actor->getY(),
                $actor->getTurnMeter(),
                $hp !== null ? sprintf(' hp=%s', $hp) : '',
            ));
        }

        return Command::SUCCESS;
    }
}

