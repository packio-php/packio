<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Infrastructure\Support;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Identity\Domain\Entity\UserSignIn;
use Packio\Identity\Domain\Repository\UserSignInRepositoryInterface;
use Packio\Identity\Infrastructure\Support\SignInRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function mb_strlen;
use function str_repeat;

#[CoversClass(SignInRecorder::class)]
final class SignInRecorderTest extends TestCase
{
    #[Test]
    public function itSavesMethodIpAndUserAgent(): void
    {
        $repository = $this->createMock(UserSignInRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with(self::callback(
            static fn (UserSignIn $signIn): bool => 'user-1' === $signIn->userUuid()
                && 'github' === $signIn->method()
                && '203.0.113.7' === $signIn->ip()
                && 'Firefox' === $signIn->userAgent(),
        ));

        $this->recorder($repository, '203.0.113.7')->record(
            new ServerRequest([
                'HTTP_USER_AGENT' => 'Firefox',
            ]),
            'user-1',
            'github',
        );
    }

    #[Test]
    public function itStoresNullsWhenRequestHasNoIpOrUserAgent(): void
    {
        $repository = $this->createMock(UserSignInRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with(self::callback(
            static fn (UserSignIn $signIn): bool => null === $signIn->ip() && null === $signIn->userAgent(),
        ));

        $this->recorder($repository, null)->record(new ServerRequest(), 'user-1', SignInRecorder::METHOD_PASSWORD);
    }

    #[Test]
    public function itTruncatesLongUserAgentTo255Characters(): void
    {
        $repository = $this->createMock(UserSignInRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with(self::callback(
            static fn (UserSignIn $signIn): bool => 255 === mb_strlen((string) $signIn->userAgent()),
        ));

        $this->recorder($repository, null)->record(
            new ServerRequest([
                'HTTP_USER_AGENT' => str_repeat('я', 300),
            ]),
            'user-1',
            SignInRecorder::METHOD_PASSWORD,
        );
    }

    #[Test]
    public function itLogsAndSwallowsSaveFailures(): void
    {
        $repository = $this->createStub(UserSignInRepositoryInterface::class);
        $repository->method('save')->willThrowException(new RuntimeException('db down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        new SignInRecorder($repository, $this->ipResolver(null), $logger)
            ->record(new ServerRequest(), 'user-1', SignInRecorder::METHOD_PASSWORD)
        ;
    }

    private function recorder(UserSignInRepositoryInterface $repository, ?string $ip): SignInRecorder
    {
        return new SignInRecorder($repository, $this->ipResolver($ip), $this->createStub(LoggerInterface::class));
    }

    private function ipResolver(?string $ip): ClientIpResolverInterface
    {
        $resolver = $this->createStub(ClientIpResolverInterface::class);
        $resolver->method('resolve')->willReturn($ip);

        return $resolver;
    }
}
