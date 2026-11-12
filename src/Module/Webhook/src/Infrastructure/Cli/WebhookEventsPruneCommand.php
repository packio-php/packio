<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Cli;

use Cake\Chronos\Chronos;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ctype_digit;
use function is_string;
use function sprintf;

final class WebhookEventsPruneCommand extends Command
{
    public function __construct(private readonly WebhookEventRepositoryInterface $webhookEventRepository)
    {
        parent::__construct('webhook:events:prune');
        $this->setDescription('Delete old webhook delivery events');
    }

    protected function configure(): void
    {
        $this->addOption('older-than-days', null, InputOption::VALUE_REQUIRED, 'Delete events older than this many days', '30');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the events without deleting them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $input->getOption('older-than-days');
        if (! is_string($days) || ! ctype_digit($days) || '0' === $days) {
            $output->writeln('<error>--older-than-days must be a positive integer.</error>');

            return Command::INVALID;
        }

        $before = Chronos::now('UTC')->subDays((int) $days);
        if ($input->getOption('dry-run')) {
            $output->writeln(sprintf('<info>Webhook events prune dry run.</info> dry_run=%d', $this->webhookEventRepository->countOlderThan($before)));

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Webhook events pruned.</info> deleted=%d', $this->webhookEventRepository->deleteOlderThan($before)));

        return Command::SUCCESS;
    }
}
