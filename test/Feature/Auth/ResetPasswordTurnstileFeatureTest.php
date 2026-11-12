<?php

declare(strict_types=1);

namespace PackioTest\Feature\Auth;

use Cake\Chronos\Chronos;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Domain\Entity\PasswordResetToken;
use Packio\Identity\Domain\Repository\PasswordResetTokenRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function hash;
use function random_bytes;

final class ResetPasswordTurnstileFeatureTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        $this->serviceOverrides['config'] = [
            'turnstile' => [
                'enabled'    => true,
                'site_key'   => 'test-site-key',
                'secret_key' => 'test-secret-key',
            ],
        ];

        parent::setUp();
    }

    #[Test]
    public function itDoesNotPersistResetCredentialsWhenTurnstileVerificationFails(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $user     = $identity->createUser(
            'reset-turnstile-' . bin2hex(random_bytes(6)) . '@example.test',
            'Reset User',
            'Password1!',
        );
        $selector  = 'sel_' . bin2hex(random_bytes(8));
        $token     = 'tok_' . bin2hex(random_bytes(16));
        $resetPage = '/auth/reset-password/' . $selector . '/' . $token;

        $this->seedPasswordResetToken($user->id, $selector, $token);

        $this->withCsrf()->post('/auth/reset-password', [
            'selector'              => $selector,
            'token'                 => $token,
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ], [
            'Referer' => $resetPage,
        ])->assertRedirect($resetPage);

        $testResponse = $this->get($resetPage, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200);
        $props = $testResponse->json()['props'] ?? [];
        self::assertSame([
            'turnstile' => ['Please complete the security challenge.'],
        ], $props['errors'] ?? null);
        self::assertArrayNotHasKey('selector', $props['old'] ?? []);
        self::assertArrayNotHasKey('token', $props['old'] ?? []);
    }

    private function seedPasswordResetToken(string $userId, string $selector, string $token): void
    {
        $repository = $this->getService(PasswordResetTokenRepositoryInterface::class);
        if (! $repository instanceof PasswordResetTokenRepositoryInterface) {
            return;
        }

        $repository->save(new PasswordResetToken(
            userUuid: $userId,
            selector: $selector,
            tokenHash: hash('sha256', $token),
            expiresAt: new Chronos('+10 minutes'),
        ));
    }
}
