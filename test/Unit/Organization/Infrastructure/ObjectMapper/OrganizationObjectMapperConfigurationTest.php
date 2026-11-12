<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Infrastructure\ObjectMapper;

use Cake\Chronos\Chronos;
use Common\Infrastructure\ObjectMapper\ObjectMapperFactory;
use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use Common\Security\SodiumSecretEncryptor;
use Cycle\ORM\EntityProxyInterface;
use Laminas\ServiceManager\ServiceManager;
use Packio\Organization\Api\Dto\OrganizationInvitationDto;
use Packio\Organization\Application\Security\OrganizationInvitationTokens;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Entity\OrganizationInvitation;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Infrastructure\ObjectMapper\InvitationTokenTransformer;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Sirix\ObjectMapper\Exception\MappingNotRegistered;
use Sirix\ObjectMapper\Runtime\ObjectMapper;

use function array_merge;
use function bin2hex;
use function dirname;
use function glob;
use function is_dir;
use function is_file;
use function random_bytes;
use function rmdir;
use function str_starts_with;
use function sys_get_temp_dir;
use function unlink;

final class OrganizationObjectMapperConfigurationTest extends TestCase
{
    private const string ENCRYPTION_KEY = 'test-key';

    private ?string $cacheDirectory = null;

    protected function tearDown(): void
    {
        if (null === $this->cacheDirectory
            || ! str_starts_with($this->cacheDirectory, sys_get_temp_dir() . '/packio-organization-object-mapper-')
            || ! is_dir($this->cacheDirectory)) {
            return;
        }

        $formatCacheDirectory = $this->cacheDirectory . '/v8';
        foreach ([...(glob($formatCacheDirectory . '/*') ?: []), ...(glob($formatCacheDirectory . '/.*') ?: [])] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($formatCacheDirectory)) {
            rmdir($formatCacheDirectory);
        }

        rmdir($this->cacheDirectory);
    }

    #[Test]
    public function itMapsOrganizationMembersAndInvitationLifecycleValues(): void
    {
        $organization = Organization::register('Example Org', 'Example Org', true);
        $organization->setIdentifier(Uuid::fromString('0186d057-4a3c-7d46-b7b7-3c46e7e3c862'));
        $organization->setCreatedAt(new Chronos('2026-08-30T10:11:12+00:00'));
        $organization->markDeleting();

        $organizationMember = OrganizationMember::join('organization-1', 'user-1', 'owner');
        $organizationMember->setIdentifier(Uuid::fromString('0186d057-4a3c-7d46-b7b7-3c46e7e3c863'));
        $organizationMember->setCreatedAt(new Chronos('2026-08-30T11:12:13+00:00'));
        $organizationMember->setUpdatedAt(new Chronos('2026-08-30T12:13:14+00:00'));

        $pending = new OrganizationInvitation(
            organizationId: 'organization-1',
            invitedByUserId: 'inviter-1',
            token: 'redacted-invitation-token',
            expiresAt: new Chronos('2099-01-01T00:00:00+00:00'),
        );
        $pending->storeToken(new SodiumSecretEncryptor(self::ENCRYPTION_KEY)->encrypt('redacted-invitation-token'));
        $pending->setIdentifier(Uuid::fromString('0186d057-4a3c-7d46-b7b7-3c46e7e3c864'));
        $pending->setCreatedAt(new Chronos('2026-08-30T13:14:15+00:00'));

        $accepted = new OrganizationInvitation(
            organizationId: 'organization-1',
            invitedByUserId: 'inviter-1',
            token: 'redacted-accepted-token',
            expiresAt: new Chronos('2026-09-01T00:00:00+00:00'),
        );
        $accepted->setIdentifier(Uuid::fromString('0186d057-4a3c-7d46-b7b7-3c46e7e3c865'));
        $accepted->setCreatedAt(new Chronos('2026-08-30T14:15:16+00:00'));
        $accepted->accept('accepted-user-1');

        $revoked = new OrganizationInvitation(
            organizationId: 'organization-1',
            invitedByUserId: 'inviter-1',
            token: 'redacted-revoked-token',
            expiresAt: new Chronos('2026-09-01T00:00:00+00:00'),
        );
        $revoked->setIdentifier(Uuid::fromString('0186d057-4a3c-7d46-b7b7-3c46e7e3c866'));
        $revoked->setCreatedAt(new Chronos('2026-08-30T15:16:17+00:00'));
        $revoked->revoke();

        $expired = new OrganizationInvitation(
            organizationId: 'organization-1',
            invitedByUserId: 'inviter-1',
            token: 'redacted-expired-token',
            expiresAt: new Chronos('2020-01-01T00:00:00+00:00'),
        );
        $expired->setIdentifier(Uuid::fromString('0186d057-4a3c-7d46-b7b7-3c46e7e3c867'));
        $expired->setCreatedAt(new Chronos('2026-08-30T16:17:18+00:00'));

        $organizationDto                = $this->objectMapper()->map($organization, OrganizationDto::class);
        $organizationMemberDto          = $this->objectMapper()->map($organizationMember, OrganizationMemberDto::class);
        $organizationInvitationDto      = $this->objectMapper()->map($pending, OrganizationInvitationDto::class);
        $acceptedDto                    = $this->objectMapper()->map($accepted, OrganizationInvitationDto::class);
        $revokedDto                     = $this->objectMapper()->map($revoked, OrganizationInvitationDto::class);
        $expiredDto                     = $this->objectMapper()->map($expired, OrganizationInvitationDto::class);

        self::assertSame('example-org', $organizationDto->slug);
        self::assertFalse($organizationDto->active);
        self::assertSame('deleting', $organizationDto->status);
        self::assertSame('2026-08-30T10:11:12+00:00', $organizationDto->createdAt);
        self::assertSame('', $organizationDto->updatedAt);

        self::assertSame('owner', $organizationMemberDto->role);
        self::assertSame('2026-08-30T12:13:14+00:00', $organizationMemberDto->updatedAt);

        self::assertSame('pending', $organizationInvitationDto->status);
        self::assertSame('redacted-invitation-token', $organizationInvitationDto->token);
        self::assertSame('redacted-accepted-token', $acceptedDto->token);
        self::assertNull($organizationInvitationDto->acceptedByUserId);
        self::assertSame('', $organizationInvitationDto->updatedAt);
        self::assertSame('', $organizationInvitationDto->acceptedAt);
        self::assertSame('', $organizationInvitationDto->revokedAt);
        self::assertSame('accepted', $acceptedDto->status);
        self::assertSame('accepted-user-1', $acceptedDto->acceptedByUserId);
        self::assertNotSame('', $acceptedDto->acceptedAt);
        self::assertSame('revoked', $revokedDto->status);
        self::assertNotSame('', $revokedDto->revokedAt);
        self::assertSame('expired', $expiredDto->status);
    }

    #[Test]
    public function itAcceptsOnlyDirectCycleProxyChildren(): void
    {
        $directOrganizationCycleProxy = new DirectOrganizationCycleProxy('Proxy Org', 'Proxy Org');
        $directOrganizationCycleProxy->setIdentifier(Uuid::fromString('0186d057-4a3c-7d46-b7b7-3c46e7e3c868'));
        $directOrganizationCycleProxy->setCreatedAt(new Chronos('2026-08-30T10:11:12+00:00'));

        $objectMapper = $this->objectMapper();

        self::assertSame('proxy-org', $objectMapper->map($directOrganizationCycleProxy, OrganizationDto::class)->slug);

        $ordinaryOrganizationSubclass = new OrdinaryOrganizationSubclass('Ordinary Org', 'Ordinary Org');
        $ordinaryOrganizationSubclass->setIdentifier(Uuid::fromString('0186d057-4a3c-7d46-b7b7-3c46e7e3c869'));
        $ordinaryOrganizationSubclass->setCreatedAt(new Chronos('2026-08-30T10:11:12+00:00'));

        $this->expectException(MappingNotRegistered::class);
        $objectMapper->map($ordinaryOrganizationSubclass, OrganizationDto::class);
    }

    private function objectMapper(): ObjectMapper
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/packio-organization-object-mapper-' . bin2hex(random_bytes(8));
        $organizationConfig   = require dirname(__DIR__, 5) . '/src/Module/Organization/config/object-mapper.config.php';

        $objectMapper = (new ObjectMapperFactory())(new ServiceManager([
            'services' => [
                'config'                                      => [
                    'object_mapper' => array_merge($organizationConfig['object_mapper'], [
                        'cache_dir'          => $this->cacheDirectory,
                        'generate_on_demand' => false,
                        'transformers'       => [
                            ChronosToAtomStringTransformer::class,
                            NullableChronosToAtomStringTransformer::class,
                            UuidInterfaceToStringTransformer::class,
                            InvitationTokenTransformer::class,
                        ],
                    ]),
                ],
                ChronosToAtomStringTransformer::class         => new ChronosToAtomStringTransformer(),
                NullableChronosToAtomStringTransformer::class => new NullableChronosToAtomStringTransformer(),
                UuidInterfaceToStringTransformer::class       => new UuidInterfaceToStringTransformer(),
                InvitationTokenTransformer::class             => new InvitationTokenTransformer(new OrganizationInvitationTokens(new SodiumSecretEncryptor(self::ENCRYPTION_KEY))),
            ],
        ]));
        $objectMapper->warmup();

        return $objectMapper;
    }
}

final class DirectOrganizationCycleProxy extends Organization implements EntityProxyInterface {}

final class OrdinaryOrganizationSubclass extends Organization {}
