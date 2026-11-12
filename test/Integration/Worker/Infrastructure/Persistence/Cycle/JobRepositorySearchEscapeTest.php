<?php

declare(strict_types=1);

namespace PackioTest\Integration\Worker\Infrastructure\Persistence\Cycle;

use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\Dto\JobFilterCriteria;
use Packio\Worker\Api\JobApiFacadeInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function array_map;
use function array_values;

final class JobRepositorySearchEscapeTest extends IntegrationTestCase
{
    #[Test]
    public function percentInSearchIsLiteral(): void
    {
        $organizationId = Uuid::uuid7()->toString();
        $facade         = $this->getService(JobApiFacadeInterface::class);
        $facade->enqueue($organizationId, '1000', []);
        $literal = $facade->enqueue($organizationId, 'rate.100%', [])->id;

        self::assertSame([$literal], $this->search($organizationId, '100%'));
    }

    #[Test]
    public function underscoreInSearchIsLiteral(): void
    {
        $organizationId = Uuid::uuid7()->toString();
        $facade         = $this->getService(JobApiFacadeInterface::class);
        $facade->enqueue($organizationId, 'axb', []);
        $literal = $facade->enqueue($organizationId, 'a_b', [])->id;

        self::assertSame([$literal], $this->search($organizationId, 'a_b'));
    }

    #[Test]
    public function statusCountsTreatSearchLiterally(): void
    {
        $organizationId = Uuid::uuid7()->toString();
        $facade         = $this->getService(JobApiFacadeInterface::class);
        $facade->enqueue($organizationId, '1000', []);
        $facade->enqueue($organizationId, 'rate.100%', []);

        $counts = $facade->countAllByStatus(new JobFilterCriteria(organizationId: $organizationId, search: '100%'));

        self::assertSame(1, $counts['pending']);
    }

    /** @return list<string> */
    private function search(string $organizationId, string $search): array
    {
        $facade = $this->getService(JobApiFacadeInterface::class);
        $result = $facade->findPaginatedAll(new PaginationCriteria(), new JobFilterCriteria(organizationId: $organizationId, search: $search));

        return array_values(array_map(static fn (JobDto $jobDto): string => $jobDto->id, $result->items));
    }
}
