<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Cli;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

final class PasswordResetCleanupCommand extends Command
{
    public function __construct(private readonly IdentityApiFacadeInterface $identityApiFacade)
    {
        parent::__construct('password-reset:cleanup');
        $this->setDescription('Clean up expired and used password reset tokens');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted = $this->identityApiFacade->cleanupExpiredPasswordResetTokens();
        $output->writeln(sprintf('<info>Deleted %d expired/reset token(s)</info>', $deleted));

        return self::SUCCESS;
    }
}
