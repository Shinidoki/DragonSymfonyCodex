<?php

namespace App\Command;

use App\Game\Application\Techniques\TechniqueImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:techniques:import',
    description: 'Import/update technique definitions from JSON files (DB is source of truth).',
)]
final class GameTechniquesImportCommand extends Command
{
    public function __construct(private readonly TechniqueImportService $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path to a JSON file or directory of JSON files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string)$input->getOption('path');
        if ($path === '') {
            $output->writeln('<error>--path is required</error>');
            return Command::INVALID;
        }

        $files = [];
        if (is_dir($path)) {
            $entries = glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json') ?: [];
            sort($entries);
            $files = $entries;
        } elseif (is_file($path)) {
            $files = [$path];
        } else {
            $output->writeln('<error>--path must be an existing file or directory</error>');
            return Command::INVALID;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $json = file_get_contents($file);
            if ($json === false) {
                $output->writeln(sprintf('<error>Failed reading %s</error>', $file));
                return Command::FAILURE;
            }

            $result  = $this->importer->importFromJsonString($json);
            $created += $result->created;
            $updated += $result->updated;
            $skipped += $result->skipped;
        }

        $output->writeln(sprintf('Imported techniques: created=%d updated=%d skipped=%d', $created, $updated, $skipped));

        return Command::SUCCESS;
    }
}

