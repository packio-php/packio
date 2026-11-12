<?php

declare(strict_types=1);

namespace PackioTest\Feature\Audit;

use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_filter;
use function array_map;
use function array_values;
use function bin2hex;
use function random_bytes;
use function range;
use function ucfirst;

final class AuditFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itRecordsALifecycleAuditOperationOnlyOnce(): void
    {
        $marker              = 'idempotent-audit-' . bin2hex(random_bytes(6));
        $userDto             = $this->createUser('idempotent-audit-owner');
        $organizationDto     = $this->createOrganization($userDto);
        $operationKey        = 'source-delete-initiation:source-1:' . $marker;

        /** @var AuditApiFacadeInterface $audit */
        $audit = $this->getService(AuditApiFacadeInterface::class);
        self::assertTrue($audit->recordOnce(
            $operationKey,
            'source.delete.initiated',
            [
                'marker' => $marker,
            ],
            organizationId: $organizationDto->id,
            sourceId: 'source-1',
            actorId: $userDto->id,
            subjectType: 'source',
            subjectId: 'source-1',
        ));
        self::assertFalse($audit->recordOnce(
            $operationKey,
            'source.delete.initiated',
            [
                'marker' => $marker,
            ],
            organizationId: $organizationDto->id,
            sourceId: 'source-1',
            actorId: $userDto->id,
            subjectType: 'source',
            subjectId: 'source-1',
        ));

        $entries = array_values(array_filter(
            $audit->recentBySource($organizationDto->id, 'source-1', 10),
            static fn (object $entry): bool => ($entry->context['marker'] ?? null) === $marker,
        ));
        self::assertCount(1, $entries);
    }

    #[Test]
    public function itRecordsAndFiltersAuditEntriesPerOrganization(): void
    {
        $marker              = 'stage4-org-audit-' . bin2hex(random_bytes(6));
        $userDto             = $this->createUser('owner');
        $foreignOwner        = $this->createUser('foreign-owner');
        $organizationDto     = $this->createOrganization($userDto);
        $foreignOrganization = $this->createOrganization($foreignOwner);

        /** @var AuditApiFacadeInterface $audit */
        $audit = $this->getService(AuditApiFacadeInterface::class);
        $audit->record(
            'source.sync',
            [
                'status'        => 'ok',
                'stage4_marker' => $marker,
            ],
            $organizationDto->id,
            '',
            '',
            $userDto->id,
        );
        $audit->record(
            'source.sync',
            [
                'status'        => 'ok',
                'stage4_marker' => $marker,
            ],
            $organizationDto->id,
            '',
            '',
            $userDto->id,
        );
        $audit->record(
            'source.sync',
            [
                'status'        => 'foreign',
                'stage4_marker' => $marker,
            ],
            $foreignOrganization->id,
            '',
            '',
            $foreignOwner->id,
        );

        $events       = $audit->recentByOrganization($organizationDto->id, 10);
        $stage4Events = array_values(array_filter(
            $events,
            static fn (object $entry): bool => ($entry->context['stage4_marker'] ?? null) === $marker,
        ));

        self::assertCount(2, $stage4Events);
        foreach ($stage4Events as $stage4Event) {
            self::assertSame('source.sync', $stage4Event->event);
            self::assertSame('ok', $stage4Event->context['status'] ?? null);
        }
    }

    #[Test]
    public function itFiltersAuditEntriesBySourceAndSourceId(): void
    {
        $marker          = 'stage4-source-audit-' . bin2hex(random_bytes(6));
        $userDto         = $this->createUser('owner');
        $organizationDto = $this->createOrganization($userDto);
        $sourceDto       = $this->createSource($organizationDto, 'audit/alpha-source-' . bin2hex(random_bytes(6)));
        $secondSource    = $this->createSource($organizationDto, 'audit/beta-source-' . bin2hex(random_bytes(6)));

        /** @var AuditApiFacadeInterface $audit */
        $audit = $this->getService(AuditApiFacadeInterface::class);
        $audit->record(
            'source.sync',
            [
                'source'        => 'alpha',
                'stage4_marker' => $marker,
            ],
            $organizationDto->id,
            $sourceDto->id,
            'alpha/package',
            $userDto->id,
        );
        $audit->record(
            'source.sync',
            [
                'source' => 'beta',
            ],
            $organizationDto->id,
            $secondSource->id,
            'beta/package',
            $userDto->id,
        );

        $entries       = $audit->recentBySource($organizationDto->id, $sourceDto->id, 10);
        $stage4Entries = array_values(array_filter(
            $entries,
            static fn (object $entry): bool => ($entry->context['stage4_marker'] ?? null) === $marker,
        ));

        self::assertCount(1, $stage4Entries);
        self::assertSame('alpha', $stage4Entries[0]->context['source'] ?? null);
        self::assertSame('source.sync', $stage4Entries[0]->event);
    }

    #[Test]
    public function itFiltersAuditEntriesByGenericSubject(): void
    {
        $marker       = 'user-audit-' . bin2hex(random_bytes(6));
        $userDto      = $this->createUser('audit-actor');
        $targetUser   = $this->createUser('audit-target');
        $otherUser    = $this->createUser('audit-other');

        /** @var AuditApiFacadeInterface $audit */
        $audit = $this->getService(AuditApiFacadeInterface::class);
        $audit->record(
            'identity.user.banned',
            [
                'reason' => 'Violation of project rules.',
                'marker' => $marker,
            ],
            actorId: $userDto->id,
            subjectType: 'user',
            subjectId: $targetUser->id,
        );
        $audit->record(
            'identity.user.unbanned',
            [
                'reason' => 'Ban lifted by administrator.',
                'marker' => $marker,
            ],
            actorId: $userDto->id,
            subjectType: 'user',
            subjectId: $targetUser->id,
        );
        $audit->record(
            'identity.user.banned',
            [
                'reason' => 'Violation of project rules.',
                'marker' => $marker,
            ],
            actorId: $userDto->id,
            subjectType: 'user',
            subjectId: $otherUser->id,
        );

        $entries = $audit->recentBySubject('user', $targetUser->id, 10);

        self::assertCount(2, $entries);
        self::assertSame($userDto->id, $entries[0]->actorId);
        self::assertSame('user', $entries[0]->subjectType);
        self::assertSame($targetUser->id, $entries[0]->subjectId);
        self::assertSame($marker, $entries[0]->context['marker']);
    }

    #[Test]
    public function itAppliesSubjectEventFilteringBeforeTheHistoryLimit(): void
    {
        $userDto    = $this->createUser('moderation-filter-actor');
        $targetUser = $this->createUser('moderation-filter-target');

        /** @var AuditApiFacadeInterface $audit */
        $audit = $this->getService(AuditApiFacadeInterface::class);
        $audit->record(
            'identity.user.banned',
            [
                'reason' => 'Violation of project rules.',
            ],
            actorId: $userDto->id,
            subjectType: 'user',
            subjectId: $targetUser->id,
        );
        $audit->record(
            'identity.user.unbanned',
            [
                'note' => 'Ban lifted by administrator.',
            ],
            actorId: $userDto->id,
            subjectType: 'user',
            subjectId: $targetUser->id,
        );

        foreach (range(1, 21) as $index) {
            $audit->record(
                'identity.user.profile_viewed',
                [
                    'index' => $index,
                ],
                actorId: $userDto->id,
                subjectType: 'user',
                subjectId: $targetUser->id,
            );
        }

        $entries = $audit->recentBySubjectAndEvents('user', $targetUser->id, [
            'identity.user.banned',
            'identity.user.unbanned',
        ], 20);

        self::assertCount(2, $entries);
        self::assertContains('identity.user.banned', array_map(
            static fn (object $entry): string => $entry->event,
            $entries,
        ));
        self::assertContains('identity.user.unbanned', array_map(
            static fn (object $entry): string => $entry->event,
            $entries,
        ));
    }

    private function createUser(string $prefix): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            $prefix . '-' . bin2hex(random_bytes(4)) . '@example.test',
            ucfirst($prefix) . ' User',
            self::PASSWORD,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function createOrganization(UserDto $userDto): OrganizationDto
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return $organizations->registerOrganization(
            'audit-org-' . bin2hex(random_bytes(6)),
            $userDto->id,
        );
    }

    private function createSource(OrganizationDto $organizationDto, string $name): SourceDto
    {
        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);

        return $sources->registerSource(new SourceRegistrationRequestDto(
            'github',
            'https://github.com/' . $name,
            $organizationDto->id,
        ));
    }
}
