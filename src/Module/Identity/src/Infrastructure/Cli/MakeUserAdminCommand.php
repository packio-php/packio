<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Cli;

use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function in_array;
use function sprintf;
use function trim;

final class MakeUserAdminCommand extends Command
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
        parent::__construct('user:make-admin');
        $this->setDescription('Grant the admin role to a user');
        $this->addArgument('email', InputArgument::REQUIRED, 'User email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = trim((string) $input->getArgument('email'));
        if ('' === $email) {
            $output->writeln('<error>Email is required.</error>');

            return self::INVALID;
        }

        $user = $this->userRepository->findByEmail($email);
        if (! $user instanceof User) {
            $output->writeln(sprintf('<error>User "%s" was not found.</error>', $email));

            return self::FAILURE;
        }

        $roles = $user->roles();
        if (in_array('admin', $roles, true)) {
            $output->writeln(sprintf('<info>User "%s" is already an admin.</info>', $user->email()));

            return self::SUCCESS;
        }

        $roles[] = 'admin';
        $user->replaceRoles($roles);
        $this->userRepository->save($user);

        $output->writeln(sprintf('<info>User "%s" is now an admin.</info>', $user->email()));

        return self::SUCCESS;
    }
}
