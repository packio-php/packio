<?php

declare(strict_types=1);

namespace Common\Infrastructure\Cli;

use Psr\Log\LoggerInterface;
use Sirix\ObjectMapper\Exception\MappingCompilationFailed;
use Sirix\ObjectMapper\Runtime\ObjectMapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;

final class ObjectMapperWarmupCommand extends Command
{
    public function __construct(
        private readonly ObjectMapper $objectMapper,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct('object-mapper:warmup');
        $this->setDescription('Compile registered object mapper pairs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Object mapper warmup started.');

        try {
            $warmedMappings = $this->objectMapper->warmup();
        } catch (MappingCompilationFailed $exception) {
            $this->logger->error('Object mapper warmup failed.', [
                'exception' => $exception,
            ]);
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return self::FAILURE;
        }

        $this->logger->info('Object mapper warmup completed.', [
            'mapping_count' => count($warmedMappings),
        ]);
        $output->writeln(sprintf('<info>Warmed %d object mapper(s).</info>', count($warmedMappings)));

        foreach ($warmedMappings as $warmedMapping) {
            $output->writeln(sprintf('  <info>%s</info>', $warmedMapping));
        }

        return self::SUCCESS;
    }
}
