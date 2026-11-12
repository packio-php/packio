<?php

declare(strict_types=1);

namespace PackioTest\Unit\Support\Base;

use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use ReflectionProperty;

final class FeatureTestCaseTest extends TestCase
{
    #[Test]
    #[DataProvider('safeMethods')]
    public function withCsrfDoesNotAttachBrowserHeadersToSafeRequests(string $method): void
    {
        $featureTestCase = new class('feature-helper') extends FeatureTestCase {};
        $this->setPrivateProperty($featureTestCase, 'ensureCsrf', true);
        $this->setPrivateProperty($featureTestCase, 'csrfToken', 'test-csrf-token');

        $serverRequest = $this->buildRequest($featureTestCase, $method);

        self::assertFalse($serverRequest->hasHeader('X-CSRF-Token'));
        self::assertFalse($serverRequest->hasHeader('Origin'));
    }

    /** @return array<string, array{string}> */
    public static function safeMethods(): array
    {
        return [
            'GET'     => ['GET'],
            'HEAD'    => ['HEAD'],
            'OPTIONS' => ['OPTIONS'],
        ];
    }

    private function setPrivateProperty(FeatureTestCase $featureTestCase, string $property, mixed $value): void
    {
        new ReflectionProperty(FeatureTestCase::class, $property)->setValue($featureTestCase, $value);
    }

    private function buildRequest(FeatureTestCase $featureTestCase, string $method): ServerRequestInterface
    {
        $request = new ReflectionMethod(FeatureTestCase::class, 'buildRequest')->invoke(
            $featureTestCase,
            $method,
            '/auth/login',
            [],
            [],
            false,
        );
        self::assertInstanceOf(ServerRequestInterface::class, $request);

        return $request;
    }
}
