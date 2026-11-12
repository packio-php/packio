<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Infrastructure\Adapter\Worker;

use Common\Mail\MailerInterface;
use Common\Security\SodiumSecretEncryptor;
use Packio\Identity\Application\Command\EmailVerificationService;
use Packio\Identity\Application\Command\PasswordResetService;
use Packio\Identity\Application\Mail\AuthEmailTemplateRendererInterface;
use Packio\Identity\Domain\Repository\EmailVerificationCodeRepositoryInterface;
use Packio\Identity\Domain\Repository\PasswordResetTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Infrastructure\Adapter\Worker\AuthMailJobHandler;
use Packio\Worker\Api\Job\JobLoggerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

use function json_encode;

final class AuthMailJobHandlerTest extends TestCase
{
    private const int FAR_FUTURE = 4_102_444_800; // 2100-01-01

    #[DataProvider('invalidRequests')]
    public function testInvalidRequestsNeverQueryAccountsOrSendMail(string $request, bool $encrypt): void
    {
        $sodiumSecretEncryptor = new SodiumSecretEncryptor('auth-mail-test-key');
        $users                 = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('findById');
        $users->expects(self::never())->method('findByEmail');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $authMailJobHandler = new AuthMailJobHandler(
            $sodiumSecretEncryptor,
            new EmailVerificationService($this->createStub(EmailVerificationCodeRepositoryInterface::class), $users, $mailer, $this->createStub(AuthEmailTemplateRendererInterface::class)),
            new PasswordResetService($this->createStub(PasswordResetTokenRepositoryInterface::class), $users, $this->createStub(EventDispatcherInterface::class), [])
        );
        $jobResult = $authMailJobHandler->handle([
            'encrypted_request' => $encrypt ? $sodiumSecretEncryptor->encrypt($request) : $request,
        ], $this->createStub(JobLoggerInterface::class));
        self::assertFalse($jobResult->isSuccess());
        self::assertFalse($jobResult->shouldRetry());
        self::assertSame('Invalid authentication mail request.', $jobResult->getErrorMessage());
    }

    /** @return iterable<array{string, bool}> */
    public static function invalidRequests(): iterable
    {
        yield ['secretbox:v1:broken', false];

        yield ['secretbox:v1:AA==', false];

        yield ['{}', false];

        yield ['not-json', true];

        yield ['null', true];

        yield ['{}', true];

        yield [json_encode([
            'expires_at' => self::FAR_FUTURE,
            'action'     => 'unsupported',
        ], JSON_THROW_ON_ERROR), true];

        yield [json_encode([
            'expires_at' => self::FAR_FUTURE,
            'action'     => 'verification',
            'user_id'    => 'user',
            'force'      => 'yes',
        ], JSON_THROW_ON_ERROR), true];
    }
}
