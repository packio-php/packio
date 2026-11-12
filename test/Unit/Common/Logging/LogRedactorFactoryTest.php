<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Logging;

use Common\Logging\LogRedactorFactory;
use Common\Logging\SafeExceptionContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_fill;
use function json_encode;

final class LogRedactorFactoryTest extends TestCase
{
    public function testStructuredCredentialsAndTextAreRedactedWithoutChangingDiagnosticMetadata(): void
    {
        $context = [
            'authorization_id' => 'auth-1',
            'source_id'        => 'source-1',
            'line'             => 42,
            'retry'            => false,
            'headers'          => [
                'Authorization' => 'Bearer private-secret',
                'PRIVATE-TOKEN' => 'private-secret',
            ],
            'nested'           => [
                'access_token' => 'private-secret',
                'clientSecret' => 'private-secret',
                'credentials'  => 'private-secret',
            ],
            'exception'        => [
                'message'  => 'Request https://user:private-secret@host failed',
                'previous' => [
                    'message' => 'token=private-secret',
                ],
            ],
        ];
        $redacted = SafeExceptionContext::redactContext($context);
        self::assertStringNotContainsString('private-secret', json_encode($redacted, JSON_THROW_ON_ERROR));
        self::assertSame('auth-1', $redacted['authorization_id']);
        self::assertSame('source-1', $redacted['source_id']);
        self::assertSame(42, $redacted['line']);
        self::assertFalse($redacted['retry']);
        self::assertSame('private-secret', $context['nested']['access_token']);
    }

    public function testTraversalLimitsOmitUnprocessedSecretsAndStateDoesNotLeakBetweenCalls(): void
    {
        $redactor = LogRedactorFactory::create();
        $context  = [
            'password' => 'private-secret',
        ];
        for ($depth = 0; $depth < 25; ++$depth) {
            $context = [
                'nested' => $context,
            ];
        }
        self::assertStringNotContainsString('private-secret', json_encode($redactor->redact($context), JSON_THROW_ON_ERROR));
        $wide = array_fill(0, 150, [
            'token' => 'private-secret',
        ]);
        self::assertStringNotContainsString('private-secret', json_encode($redactor->redact($wide), JSON_THROW_ON_ERROR));
        self::assertSame([
            'message' => 'Connection refused',
            'line'    => 12,
        ], $redactor->redact([
            'message' => 'Connection refused',
            'line'    => 12,
        ]));
    }

    public function testDeepExceptionChainsAreBoundedAndContainNoUnprocessedMessages(): void
    {
        $exception = new RuntimeException('password=private-secret');
        for ($depth = 0; $depth < 30; ++$depth) {
            $exception = new RuntimeException('token=private-secret', 0, $exception);
        }
        $context = SafeExceptionContext::exception($exception);
        self::assertStringNotContainsString('private-secret', json_encode($context, JSON_THROW_ON_ERROR));
        self::assertStringContainsString('REDACTED', json_encode($context, JSON_THROW_ON_ERROR));
    }
}
