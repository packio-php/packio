<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Persistence\Cycle;

use Packio\Source\Domain\Entity\Source;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceRepository;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class SourceRepositoryFindByUrlTest extends IntegrationTestCase
{
    #[Test]
    public function findByUrlIgnoresSourcesWithoutOrganization(): void
    {
        $repository = $this->getService(SourceRepository::class);
        self::assertInstanceOf(SourceRepository::class, $repository);

        $url = 'https://example.test/' . Uuid::uuid7()->toString();
        $repository->save(new Source(organizationId: '', provider: 'github', canonicalUrl: $url, displayName: 'orphan'));

        self::assertNull($repository->findByUrl($url, 'org-a'));
    }
}
