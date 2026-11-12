<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Validation;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Validation\JsonValidationFailureResponseFactory;
use Packio\Application\Validation\RequestAwareValidationFailureResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Validation\ApiValidationFailureResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function json_decode;

final class RequestAwareValidationFailureResponseFactoryTest extends TestCase
{
    #[Test]
    public function apiPathSelectsTheApiValidationEnvelope(): void
    {
        $response = $this->factory()->validation(
            $this->request('/api/v1/organizations'),
            [
                'name' => ['Name is required.'],
            ],
            'Invalid request data.',
            422,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'error' => [
                'code'    => 'validation_failed',
                'message' => 'Invalid request data.',
                'details' => [
                    'name' => ['Name is required.'],
                ],
            ],
        ], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function apiPathSelectsTheApiEnvelopeEvenWithoutAcceptHeader(): void
    {
        $response = $this->factory()->validation(
            $this->request('/api/v1/organizations', accept: null),
            [
                'name' => ['Name is required.'],
            ],
        );

        self::assertSame([
            'error' => [
                'code'    => 'validation_failed',
                'message' => 'Invalid request data.',
                'details' => [
                    'name' => ['Name is required.'],
                ],
            ],
        ], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function nonApiPathKeepsTheFlatJsonContract(): void
    {
        $response = $this->factory()->validation(
            $this->request('/organizations'),
            [
                'name' => ['Name is required.'],
            ],
            'Invalid request data.',
            422,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'error'    => 'Invalid request data.',
            'messages' => [
                'name' => ['Name is required.'],
            ],
        ], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function nonApiPathNeverUsesTheApiEnvelopeEvenWithJsonAccept(): void
    {
        $response = $this->factory()->validation(
            $this->request('/organizations', accept: 'application/json'),
            [
                'name' => ['Name is required.'],
            ],
        );

        self::assertSame([
            'error'    => 'Invalid request data.',
            'messages' => [
                'name' => ['Name is required.'],
            ],
        ], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function customStatusIsPreservedByBothAdapters(): void
    {
        self::assertSame(418, $this->factory()->validation(
            $this->request('/api/v1/organizations'),
            [],
            'Invalid request data.',
            418,
        )->getStatusCode());

        self::assertSame(418, $this->factory()->validation(
            $this->request('/organizations'),
            [],
            'Invalid request data.',
            418,
        )->getStatusCode());
    }

    private function factory(): RequestAwareValidationFailureResponseFactory
    {
        return new RequestAwareValidationFailureResponseFactory(
            jsonValidationFailureResponseFactory: new JsonValidationFailureResponseFactory(),
            apiValidationFailureResponseFactory: new ApiValidationFailureResponseFactory(new ApiErrorResponseFactory()),
        );
    }

    private function request(string $path, ?string $accept = 'application/json'): ServerRequestInterface
    {
        $serverRequest = new ServerRequest([], [], $path, 'POST');

        return null === $accept ? $serverRequest : $serverRequest->withHeader('Accept', $accept);
    }
}
