<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Validation;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Validation\ValidationFailureRequestPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationFailureRequestPolicy::class)]
final class ValidationFailureRequestPolicyTest extends TestCase
{
    #[Test]
    public function itPrefersInertiaRedirectsOverJsonAcceptHeaders(): void
    {
        $serverRequest = new ServerRequest([], [], '/organization/acme/settings', 'POST')
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Inertia', 'true')
        ;

        self::assertFalse(new ValidationFailureRequestPolicy()->wantsJson($serverRequest));
    }

    #[Test]
    public function apiPathBeatsTheInertiaHeader(): void
    {
        $serverRequest = new ServerRequest([], [], '/api/example', 'POST')
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Inertia', 'true')
        ;

        self::assertTrue(new ValidationFailureRequestPolicy()->wantsJson($serverRequest));
    }

    #[Test]
    public function itTreatsApiPathsAsJsonWithoutAnAcceptHeader(): void
    {
        self::assertTrue(new ValidationFailureRequestPolicy()->wantsJson(new ServerRequest([], [], '/api/example', 'POST')));
    }

    #[Test]
    public function itProjectsOnlyAllowlistedOldInput(): void
    {
        $oldInput = new ValidationFailureRequestPolicy()->allowedOldInput(
            [
                'name'     => 'Packio',
                'password' => 'not-retained',
            ],
            ['name'],
        );

        self::assertSame([
            'name' => 'Packio',
        ], $oldInput);
    }
}
