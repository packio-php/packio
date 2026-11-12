<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Cli;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

final class EmailVerificationCleanupCommand extends Command
{
    public function __construct(private readonly IdentityApiFacadeInterface $identityApiFacade)
    {
        parent::__construct('email-verification:cleanup');
        $this->setDescription('Clean up expired email verification codes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted = $this->identityApiFacade->cleanupExpiredEmailVerificationCodes();
        $output->writeln(sprintf('<info>Deleted %d expired verification code(s)</info>', $deleted));

        return self::SUCCESS;
    }
}
