<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Cli;

use Cake\Chronos\Chronos;
use Packio\Identity\Domain\Repository\UserSignInRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function ctype_digit;
use function is_scalar;
use function sprintf;

final class SignInHistoryPruneCommand extends Command
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly UserSignInRepositoryInterface $userSignInRepository,
        private readonly array $config,
    ) {
        parent::__construct('sign-in-history:prune');
        $this->setDescription('Delete sign-in history entries older than SIGN_IN_HISTORY_RETENTION_DAYS');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $this->config['sign_in_history']['retention_days'] ?? null;
        $days = is_scalar($days) ? (string) $days : '';
        if (! ctype_digit($days) || 0 === (int) $days) {
            $output->writeln('<error>SIGN_IN_HISTORY_RETENTION_DAYS must be a positive integer.</error>');

            return Command::INVALID;
        }

        $before = Chronos::now('UTC')->subDays((int) $days);
        $output->writeln(sprintf('<info>Sign-in history pruned.</info> older_than_days=%s deleted=%d', $days, $this->userSignInRepository->deleteOlderThan($before)));

        return Command::SUCCESS;
    }
}
