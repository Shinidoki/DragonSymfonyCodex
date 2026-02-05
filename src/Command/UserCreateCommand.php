<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a user (optionally admin) in the database.',
)]
final class UserCreateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface      $entityManager,
        private readonly UserRepository              $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Username (unique)')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Plain password (discouraged; prefer prompt)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = (string)$input->getOption('username');
        $username = trim($username);

        if ($username === '') {
            $io->error('--username is required');
            return Command::INVALID;
        }

        if ($this->users->findOneBy(['username' => $username]) !== null) {
            $io->error(sprintf('Username already exists: %s', $username));
            return Command::FAILURE;
        }

        $plainPassword = (string)$input->getOption('password');
        if (trim($plainPassword) === '') {
            if (!$input->isInteractive()) {
                $io->error('Password is required. Use interactive prompt or pass --password.');
                return Command::INVALID;
            }

            $question = new Question('Password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $question->setValidator(static function (mixed $value): string {
                $password = is_string($value) ? $value : '';
                if (trim($password) === '') {
                    throw new \RuntimeException('Password must not be empty.');
                }

                return $password;
            });

            $answer = $this->getHelper('question')->ask($input, $output, $question);
            if (!is_string($answer)) {
                $io->error('Password prompt failed.');
                return Command::FAILURE;
            }

            $plainPassword = $answer;
        }

        $user = (new User())
            ->setUsername($username);

        if ((bool)$input->getOption('admin')) {
            $user->setRoles(['ROLE_ADMIN']);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Created user #%d (%s)%s.',
            (int)$user->getId(),
            $user->getUserIdentifier(),
            in_array('ROLE_ADMIN', $user->getRoles(), true) ? ' with ROLE_ADMIN' : '',
        ));

        return Command::SUCCESS;
    }
}
