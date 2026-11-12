<?php

declare(strict_types=1);

namespace PackioTest\Feature\Auth;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Domain\Repository\PasswordResetTokenRepositoryInterface;
use Packio\Identity\Infrastructure\Adapter\Worker\AuthMailQueueAdapter;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;
use Traversable;

use function bin2hex;
use function count;
use function iterator_count;
use function random_bytes;

final class ForgotPasswordFeatureTest extends FeatureTestCase
{
    #[Test]
    public function itRendersForgotPasswordPageInInertiaMode(): void
    {
        $testResponse = $this->get('/auth/forgot-password', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200);
        self::assertSame('Auth/ForgotPassword', $testResponse->json()['component'] ?? null);
    }

    #[Test]
    public function itReturnsValidationErrorForInvalidForgotPasswordEmail(): void
    {
        $this->withCsrf()->post('/auth/forgot-password', [
            'email' => 'invalid-email',
        ])->assertRedirect('/auth/forgot-password');

        $testResponse = $this->get('/auth/forgot-password', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200);

        self::assertArrayHasKey('errors', $testResponse->json()['props'] ?? []);
        self::assertNotSame('', $testResponse->json()['props']['errors']['email'][0] ?? null);
    }

    #[Test]
    public function itCreatesPasswordResetTokenForExistingEmail(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $email    = 'forgot-existing-' . bin2hex(random_bytes(6)) . '@example.test';

        $user = $identity->createUser(
            $email,
            'Forgot Existing User',
            'Password1!',
        );

        $before = $this->countPasswordResetTokensForUser($user->id);

        $this->withCsrf()->post('/auth/forgot-password', [
            'email' => $email,
        ])->assertRedirect('/auth/forgot-password');

        self::assertSame($before, $this->countPasswordResetTokensForUser($user->id));
        $jobs = $this->getService(JobRepositoryInterface::class)->findByOrganization(AuthMailQueueAdapter::SYSTEM_SCOPE);
        self::assertCount(1, $jobs);
        $jobs[0]->markRunning('test-mail-worker');
        $this->getService(JobProcessor::class)->process($jobs[0]);
        $after = $this->countPasswordResetTokensForUser($user->id);
        self::assertSame($before + 1, $after);
    }

    #[Test]
    public function itReturnsValidationErrorForEmptyEmail(): void
    {
        $this->withCsrf()->post('/auth/forgot-password', [
            'email' => '',
        ])->assertRedirect('/auth/forgot-password');

        $testResponse = $this->get('/auth/forgot-password', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200);

        self::assertArrayHasKey('errors', $testResponse->json()['props'] ?? []);
        self::assertSame('Email cannot be blank.', $testResponse->json()['props']['errors']['email'][0] ?? null);
    }

    #[Test]
    public function itSendsPasswordResetRequestWithoutLeakForUnknownEmail(): void
    {
        $before = $this->countPasswordResetTokens();

        $this->withCsrf()->post('/auth/forgot-password', [
            'email' => 'missing-' . bin2hex(random_bytes(6)) . '@example.test',
        ])->assertRedirect('/auth/forgot-password');

        self::assertSame($before, $this->countPasswordResetTokens());
    }

    private function countPasswordResetTokens(): int
    {
        $repository = $this->getService(PasswordResetTokenRepositoryInterface::class);

        $tokens = $repository->findAll([]);
        if ($tokens instanceof Traversable) {
            return iterator_count($tokens);
        }

        return count($tokens);
    }

    private function countPasswordResetTokensForUser(string $userId): int
    {
        $repository = $this->getService(PasswordResetTokenRepositoryInterface::class);

        $tokens = $repository->findAll([
            'userUuid' => $userId,
        ]);
        if ($tokens instanceof Traversable) {
            return iterator_count($tokens);
        }

        return count($tokens);
    }
}
