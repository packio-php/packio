<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Cli;

use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;

final class SourceWebhookSecretsEncryptCommand extends Command
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly SourceWebhookSecrets $sourceWebhookSecrets,
    ) {
        parent::__construct('source:webhook-secrets:encrypt');
        $this->setDescription('Encrypt plaintext Source webhook secrets at rest');
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count plaintext secrets without changing them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun  = (bool) $input->getOption('dry-run');
        $count   = 0;
        $afterId = null;

        do {
            $sources = $this->sourceRepository->findBatchAfter($afterId, self::BATCH_SIZE);
            foreach ($sources as $source) {
                $afterId = $source->getIdentifier()->toString();
                $config  = $this->sourceWebhookSecrets->encryptConfiguration($source->providerConfig());
                if ($config === $source->providerConfig()) {
                    continue;
                }

                ++$count;
                if (! $dryRun) {
                    $source->replaceProviderConfig($config);
                    $this->sourceRepository->save($source);
                }
            }
        } while (self::BATCH_SIZE === count($sources));

        $output->writeln(sprintf(
            '<info>Source webhook secrets encryption completed.</info> %s=%d',
            $dryRun ? 'dry_run' : 'encrypted',
            $count,
        ));

        return Command::SUCCESS;
    }
}
