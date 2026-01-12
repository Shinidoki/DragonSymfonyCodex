<?php

namespace App\Command;

use App\Entity\LocalSession;
use App\Game\Application\Local\LongActionType;
use App\Game\Application\Local\StartLongActionHandler;
use App\Game\Application\Local\TrainingContextResolver;
use App\Game\Domain\Training\TrainingContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:local:train',
    description: 'Train for N days while in local mode (suspends local session, advances days, then resumes).',
)]
final class GameLocalTrainCommand extends Command
{
    public function __construct(
        private readonly StartLongActionHandler  $handler,
        private readonly TrainingContextResolver $contextResolver,
        private readonly EntityManagerInterface  $entityManager,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('session', null, InputOption::VALUE_REQUIRED, 'Local session id');
        $this->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Days to train', 1);
        $this->addOption('context', null, InputOption::VALUE_OPTIONAL, 'Training context (auto|wilderness|dojo|mentor)', 'auto');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionId  = (int)$input->getOption('session');
        $days       = (int)$input->getOption('days');
        $contextRaw = strtolower((string)$input->getOption('context'));

        if ($sessionId <= 0) {
            $output->writeln('<error>--session must be a positive integer</error>');
            return Command::INVALID;
        }
        if ($days <= 0) {
            $output->writeln('<error>--days must be a positive integer</error>');
            return Command::INVALID;
        }

        $context = null;

        if ($contextRaw === 'auto') {
            $session = $this->entityManager->find(LocalSession::class, $sessionId);
            if (!$session instanceof LocalSession) {
                $output->writeln('<error>Local session not found</error>');
                return Command::FAILURE;
            }

            $context = $this->contextResolver->forWorldTile($session->getWorldId(), $session->getTileX(), $session->getTileY());
        } else {
            try {
                $context = TrainingContext::from($contextRaw);
            } catch (\ValueError) {
                $output->writeln('<error>--context must be auto, wilderness, dojo, or mentor</error>');
                return Command::INVALID;
            }
        }

        $result = $this->handler->start($sessionId, $days, LongActionType::Train, $context);

        $output->writeln(sprintf(
            'Trained %d day(s) (%s). World day: %d. Session #%d resumed at (%d,%d). STR now %d.',
            $result->daysAdvanced,
            $context->value,
            $result->world->getCurrentDay(),
            (int)$result->session->getId(),
            $result->session->getPlayerX(),
            $result->session->getPlayerY(),
            $result->character->getStrength(),
        ));

        return Command::SUCCESS;
    }
}
