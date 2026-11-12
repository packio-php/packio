<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Mapper;

use Cake\Chronos\Chronos;
use Common\Security\SodiumSecretEncryptor;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Infrastructure\Mapper\SourceDtoMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

use function array_map;

#[CoversClass(SourceDtoMapper::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceDtoMapperTest extends TestCase
{
    #[Test]
    public function mapsKeepLastReleases(): void
    {
        $source = Source::register(
            organizationId: 'org-1',
            provider: 'github',
            canonicalUrl: 'https://github.com/acme/package',
            displayName: 'acme/package',
            defaultRef: 'main',
        );
        $source->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000001'));
        $source->setCreatedAt(new Chronos('2026-06-12T00:00:00+00:00'));
        $source->updateKeepLastReleases(5);

        $sourceDtoMapper = new SourceDtoMapper($this->createMock(ProviderAuthorizationRepositoryInterface::class), new SourceWebhookSecrets(new SodiumSecretEncryptor('test-webhook-key')));
        $sourceDto       = $sourceDtoMapper->map($source);

        self::assertSame(5, $sourceDto->keepLastReleases);
        self::assertSame(5, $sourceDto->toArray()['keep_last_releases']);
    }

    #[Test]
    public function mapsOrganizationSourcesWithOneAuthorizationQuery(): void
    {
        $providerAuthorization = ProviderAuthorization::authorize('org-1', 'github', 'oauth', []);
        $providerAuthorization->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-0000000000a1'));
        $other = ProviderAuthorization::authorize('org-1', 'gitlab', 'token', []);
        $other->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-0000000000a2'));

        $repository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $repository->expects(self::once())->method('findByOrganization')->with('org-1')->willReturn([$providerAuthorization, $other]);
        $repository->expects(self::never())->method('findByIdInOrganization');

        $sources = [
            $this->source('1', $providerAuthorization->getIdentifier()->toString()),
            $this->source('2', $other->getIdentifier()->toString()),
            $this->source('3', ''),
        ];

        $dtos = new SourceDtoMapper($repository, new SourceWebhookSecrets(new SodiumSecretEncryptor('test-webhook-key')))->mapForOrganization($sources, 'org-1');

        self::assertSame(['oauth', '', ''], array_map(static fn ($dto): string => $dto->authType, $dtos));
        self::assertSame([true, false, false], array_map(static fn ($dto): bool => $dto->credentialsConfigured, $dtos));
        self::assertSame([$providerAuthorization->status(), '', ''], array_map(static fn ($dto): string => $dto->authorizationStatus, $dtos));
    }

    private function source(string $n, string $authorizationId): Source
    {
        $source = Source::register(
            organizationId: 'org-1',
            provider: 'github',
            canonicalUrl: 'https://github.com/acme/package' . $n,
            displayName: 'acme/package' . $n,
            defaultRef: 'main',
            providerAuthorizationId: $authorizationId,
        );
        $source->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-00000000000' . $n));
        $source->setCreatedAt(new Chronos('2026-06-12T00:00:00+00:00'));

        return $source;
    }
}
