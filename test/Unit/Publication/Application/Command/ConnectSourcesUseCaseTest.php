<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Application\Command;

use Packio\Publication\Application\Command\ConnectSourcesCommand;
use Packio\Publication\Application\Command\ConnectSourcesUseCase;
use Packio\Publication\Application\Port\SourceConnectPort;
use Packio\Shared\Domain\Organization\ActorContext;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

use function array_unique;
use function count;
use function serialize;

final class ConnectSourcesUseCaseTest extends TestCase
{
    public function testIdempotencyKeysIncludeOrganizationProviderAuthorizationAndRepository(): void
    {
        $port = $this->createMock(SourceConnectPort::class);
        $port->method('authorize')->willReturnCallback(static fn (ConnectSourcesCommand $connectSourcesCommand): string => $connectSourcesCommand->authorizationId);
        $keys = [];
        $port->expects(self::exactly(5))->method('schedule')->willReturnCallback(static function(array $payload) use (&$keys): bool {
            $keys[] = $payload['idempotency_key'];

            return true;
        });
        $connectSourcesUseCase = new ConnectSourcesUseCase($port, new NullLogger());
        foreach ([['org', 'github', 'auth', 'a/b'], ['other', 'github', 'auth', 'a/b'], ['org', 'gitlab', 'auth', 'a/b'], ['org', 'github', 'other', 'a/b'], ['org', 'github', 'auth', 'c/d']] as [$organization, $provider, $authorization, $repository]) {
            $connectSourcesUseCase->execute(new ConnectSourcesCommand(new ActorContext($organization, 'user', 'owner'), $provider, [$repository], authorizationId: $authorization));
        }
        self::assertCount(5, array_unique($keys));
    }

    public function testTokenRetryUsesStableKeyEvenWhenAuthorizationCreationRaces(): void
    {
        $port = $this->createMock(SourceConnectPort::class);
        $port->expects(self::exactly(2))->method('authorize')->willReturnOnConsecutiveCalls('first-auth', 'concurrent-auth');
        $keys = [];
        $port->expects(self::exactly(2))->method('schedule')->willReturnCallback(static function(array $payload) use (&$keys): bool {
            $keys[] = $payload['idempotency_key'];
            self::assertArrayNotHasKey('token', $payload);
            self::assertStringNotContainsString('secret', $payload['idempotency_key']);

            return 1 === count($keys);
        });
        $connectSourcesUseCase = new ConnectSourcesUseCase($port, new NullLogger());
        $connectSourcesCommand = new ConnectSourcesCommand(new ActorContext('org', 'user', 'owner'), 'github', ['a/b'], authorizationPayload: [
            'token' => 'secret',
        ]);
        self::assertCount(1, $connectSourcesUseCase->execute($connectSourcesCommand)->queued);
        self::assertCount(1, $connectSourcesUseCase->execute($connectSourcesCommand)->skipped);
        self::assertSame($keys[0], $keys[1]);
    }

    public function testPartialFailureAndRetryDoNotDuplicateJobs(): void
    {
        $port = $this->createMock(SourceConnectPort::class);
        $port->expects(self::exactly(3))->method('authorize')->willReturn('auth');
        $stored = [];
        $fail   = true;
        $port->method('schedule')->willReturnCallback(static function(array $payload, string $organization) use (&$stored, &$fail): bool {
            self::assertSame('org', $organization);
            self::assertSame('user', $payload['requested_by_user_id']);
            if ('b/repo' === $payload['resource'] && $fail) {
                $fail = false;

                throw new RuntimeException('https://user:secret@host token=secret');
            }
            $key = $payload['idempotency_key'];
            if (isset($stored[$key])) {
                return false;
            }
            $stored[$key] = $payload;

            return true;
        });
        $connectSourcesUseCase = new ConnectSourcesUseCase($port, new NullLogger());
        $connectSourcesCommand = new ConnectSourcesCommand(new ActorContext('org', 'user', 'owner'), 'github', ['a/repo', 'b/repo', 'c/repo']);
        $result                = $connectSourcesUseCase->execute($connectSourcesCommand);
        self::assertCount(2, $result->queued);
        self::assertSame('b/repo', $result->failed[0]->repository);
        self::assertSame('internal_error', $result->failed[0]->code);
        self::assertStringNotContainsString('secret', $result->failed[0]->message);
        $result = $connectSourcesUseCase->execute($connectSourcesCommand);
        self::assertCount(1, $result->queued);
        self::assertCount(2, $result->skipped);
        self::assertSame([], $result->failed);
        self::assertCount(3, $connectSourcesUseCase->execute($connectSourcesCommand)->skipped);
        self::assertCount(3, $stored);
    }

    public function testAuthorizationFailureReturnsEveryRepositoryAndSafeCorrelatedLog(): void
    {
        $port = $this->createMock(SourceConnectPort::class);
        $port->method('authorize')->willThrowException(new RuntimeException('token=secret'));
        $port->expects(self::never())->method('schedule');
        $logger      = $this->createMock(LoggerInterface::class);
        $correlation = '';
        $logger->expects(self::once())->method('error')->willReturnCallback(static function(string $message, array $context) use (&$correlation): void {
            self::assertStringNotContainsString('secret', serialize([$message, $context]));
            $correlation = $context['correlation_id'];
        });
        $connectSourcesResult = new ConnectSourcesUseCase($port, $logger)->execute(new ConnectSourcesCommand(new ActorContext('org', 'user', 'owner'), 'github', ['a/b', 'c/d']));
        self::assertCount(2, $connectSourcesResult->failed);
        self::assertSame($correlation, $connectSourcesResult->correlationId);
    }

    public function testNonOwnerCannotAuthorizeOrSchedule(): void
    {
        $port = $this->createMock(SourceConnectPort::class);
        $port->expects(self::never())->method('authorize');
        $port->expects(self::never())->method('schedule');
        $connectSourcesResult = new ConnectSourcesUseCase($port, new NullLogger())->execute(new ConnectSourcesCommand(new ActorContext('org', 'user', 'member'), 'github', ['a/b']));
        self::assertSame('forbidden', $connectSourcesResult->failed[0]->code);
    }
}
