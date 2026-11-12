<?php

declare(strict_types=1);

namespace PackioTest\Feature\Auth;

use Common\Mail\MailerInterface;
use Common\Security\SecretEncryptorInterface;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Application\Settings\RuntimeSettingOverride;
use Packio\Admin\Application\Settings\RuntimeSettingsService;
use Packio\Identity\Api\EmailVerificationReasonType;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\EmailVerificationService;
use Packio\Identity\Domain\Entity\EmailVerificationCode;
use Packio\Identity\Domain\Repository\EmailVerificationCodeRepositoryInterface;
use Packio\Identity\Domain\Repository\PasswordResetTokenRepositoryInterface;
use Packio\Identity\Infrastructure\Adapter\Worker\AuthMailQueueAdapter;
use Packio\Worker\Api\JobApiFacadeInterface;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Fake\RecordingAuthMailer;

use function json_encode;
use function preg_match;
use function sort;
use function time;

final class AuthMailQueueFeatureTest extends FeatureTestCase
{
    private RecordingAuthMailer $recordingAuthMailer;

    protected function setUp(): void
    {
        $this->recordingAuthMailer                      = new RecordingAuthMailer();
        $this->serviceOverrides[MailerInterface::class] = $this->recordingAuthMailer;
        parent::setUp();
        $this->getService(RuntimeSettingsService::class)->updateOverrides([
            ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::On->value,
        ], null);
    }

    public function testRegistrationAndDuplicateQueueWithoutSendingOrChangingExistingAccount(): void
    {
        $form = [
            'email'                 => 'queued@example.test',
            'display_name'          => 'Original',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ];
        $this->withCsrf()->post('/auth/register', $form)->assertRedirect('/auth/verification');
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->authenticateCredentials($form['email'], $form['password']);
        self::assertNotNull($user);
        self::assertNull($this->verificationCode($user->id));
        self::assertSame([], $this->recordingAuthMailer->messages);
        $jobs = $this->jobs();
        self::assertCount(1, $jobs);
        $this->process($jobs[0]);
        self::assertCount(1, $this->recordingAuthMailer->messages);
        $code = $this->verificationCode($user->id);
        self::assertNotNull($code);
        $hash = $code->codeHash();
        $this->withCsrf()->post('/auth/register', [
            ...$form,
            'display_name'          => 'Attacker',
            'password'              => 'Different2!',
            'password_confirmation' => 'Different2!',
        ])->assertRedirect('/auth/verification');
        $jobs = $this->jobs();
        self::assertCount(2, $jobs);
        foreach ($jobs as $job) {
            if (Job::STATUS_PENDING === $job->status()) {
                $this->process($job);
            }
        }
        self::assertCount(1, $this->recordingAuthMailer->messages);
        self::assertSame($hash, $this->verificationCode($user->id)?->codeHash());
        self::assertNull($identity->authenticateCredentials($form['email'], 'Different2!'));
        self::assertSame('Original', $identity->authenticateCredentials($form['email'], 'Password1!')?->displayName);
        if (1 !== preg_match('/verification code is ([0-9]{6})/', $this->recordingAuthMailer->messages[0]['html'], $matches)) {
            self::fail('Expected authentication credential in delivered test email.');
        }
        $this->withCsrf()->post('/auth/verification/verify', [
            'code' => $matches[1],
        ])->assertRedirect('/auth/verification');
        self::assertTrue($this->getService(EmailVerificationService::class)->verifyCode($user->id, $matches[1]));
    }

    public function testPasswordResetLookupAndTokenCreationOnlyHappenInWorker(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser('reset-queue@example.test', 'Reset', 'Password1!');
        $tokens   = $this->getService(PasswordResetTokenRepositoryInterface::class);
        foreach (['reset-queue@example.test', 'missing-queue@example.test'] as $email) {
            $this->withCsrf()->post('/auth/forgot-password', [
                'email' => $email,
            ])->assertRedirect('/auth/forgot-password');
        }
        self::assertCount(0, $tokens->findAll([
            'userUuid' => $user->id,
        ]));
        self::assertSame([], $this->recordingAuthMailer->messages);
        self::assertCount(2, $this->jobs());
        foreach ($this->jobs() as $job) {
            self::assertStringNotContainsString('@example.test', json_encode($job->payload(), JSON_THROW_ON_ERROR));
            self::assertTrue($this->getService(SecretEncryptorInterface::class)->isEncrypted($job->payload()['encrypted_request']));
            $this->process($job);
        }
        self::assertCount(1, $tokens->findAll([
            'userUuid' => $user->id,
        ]));
        self::assertCount(1, $this->recordingAuthMailer->messages);
        self::assertSame('reset-queue@example.test', $this->recordingAuthMailer->messages[0]['to']);
        if (1 !== preg_match('~/auth/reset-password/([a-f0-9]{32})/([a-f0-9]{64})~', $this->recordingAuthMailer->messages[0]['html'], $matches)) {
            self::fail('Expected authentication credential in delivered test email.');
        }
        self::assertTrue($identity->validatePasswordResetToken($matches[1], $matches[2])->success);
        self::assertSame([], $this->getService(JobRepositoryInterface::class)->findByOrganization('00000000-0000-0000-0000-000000000001'));
    }

    public function testFailedDeliveryRetriesWithoutPersistingTransportSecrets(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser('retry-queue@example.test', 'Retry', 'Password1!');
        $identity->requestPasswordReset($user->email);
        $job                             = $this->jobs()[0];
        $this->recordingAuthMailer->fail = true;
        $job->markRunning('test');
        $this->getService(JobProcessor::class)->process($job);
        self::assertSame(Job::STATUS_PENDING, $job->status());
        self::assertCount(0, $this->getService(PasswordResetTokenRepositoryInterface::class)->findAll([
            'userUuid' => $user->id,
        ]));
        $logs    = $this->getService(JobApiFacadeInterface::class)->findLogsByJob($job->getIdentifier()->toString());
        $encoded = json_encode($logs, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('secret-token', $encoded);
        self::assertStringNotContainsString('private-password', $encoded);
        $this->recordingAuthMailer->fail = false;
        $this->process($job);
        self::assertCount(1, $this->recordingAuthMailer->messages);
    }

    public function testDelayedVerificationDoesNotSendAfterUserHasVerifiedEmail(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser('already-verified@example.test', 'Verified', 'Password1!');
        $identity->requestEmailVerification($user->id, true, EmailVerificationReasonType::ManualResend);
        $identity->markUserEmailAsVerified($user->id);
        $this->process($this->jobs()[0]);
        self::assertSame([], $this->recordingAuthMailer->messages);
    }

    public function testExpiredAndPlaintextRequestsNeverSendMail(): void
    {
        $queue   = $this->getService(JobApiFacadeInterface::class);
        $request = json_encode([
            'action'     => 'password_reset',
            'email'      => 'stale@example.test',
            'expires_at' => time() - 1,
        ], JSON_THROW_ON_ERROR);
        $queue->enqueue(AuthMailQueueAdapter::SYSTEM_SCOPE, AuthMailQueueAdapter::TYPE, [
            'encrypted_request' => $request,
        ]);
        $queue->enqueue(AuthMailQueueAdapter::SYSTEM_SCOPE, AuthMailQueueAdapter::TYPE, [
            'encrypted_request' => $this->getService(SecretEncryptorInterface::class)->encrypt($request),
        ]);
        $statuses = [];
        foreach ($this->jobs() as $job) {
            $job->markRunning('test');
            $this->getService(JobProcessor::class)->process($job);
            $statuses[] = $job->status();
        }
        sort($statuses);
        self::assertSame([Job::STATUS_COMPLETED, Job::STATUS_FAILED], $statuses);
        self::assertSame([], $this->recordingAuthMailer->messages);
    }

    /** @phpstan-impure */
    private function verificationCode(string $userId): ?EmailVerificationCode
    {
        return $this->getService(EmailVerificationCodeRepositoryInterface::class)->findActiveByUserUuid($userId);
    }

    /** @return list<Job> */
    private function jobs(): array
    {
        return $this->getService(JobRepositoryInterface::class)->findByOrganization(AuthMailQueueAdapter::SYSTEM_SCOPE);
    }

    private function process(Job $job): void
    {
        $job->markRunning('test');
        $this->getService(JobProcessor::class)->process($job);
        self::assertSame(Job::STATUS_COMPLETED, $job->status(), $job->errorMessage() ?? '');
    }
}
