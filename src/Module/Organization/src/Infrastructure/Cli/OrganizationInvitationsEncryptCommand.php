<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Cli;

use Packio\Organization\Application\Security\OrganizationInvitationTokens;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;

final class OrganizationInvitationsEncryptCommand extends Command
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private readonly OrganizationInvitationRepositoryInterface $organizationInvitationRepository,
        private readonly OrganizationInvitationTokens $organizationInvitationTokens,
    ) {
        parent::__construct('organization:invitations:encrypt');
        $this->setDescription('Encrypt plaintext organization invitation tokens at rest');
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count plaintext tokens without changing them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun  = (bool) $input->getOption('dry-run');
        $count   = 0;
        $afterId = null;

        do {
            $invitations = $this->organizationInvitationRepository->findBatchAfter($afterId, self::BATCH_SIZE);
            foreach ($invitations as $invitation) {
                $afterId = $invitation->getIdentifier()->toString();
                $stored  = $this->organizationInvitationTokens->encrypt($invitation->token());
                if ($stored === $invitation->token()) {
                    continue;
                }

                ++$count;
                if (! $dryRun) {
                    $invitation->storeToken($stored);
                    $this->organizationInvitationRepository->save($invitation);
                }
            }
        } while (self::BATCH_SIZE === count($invitations));

        $output->writeln(sprintf(
            '<info>Organization invitation tokens encryption completed.</info> %s=%d',
            $dryRun ? 'dry_run' : 'encrypted',
            $count,
        ));

        return Command::SUCCESS;
    }
}
