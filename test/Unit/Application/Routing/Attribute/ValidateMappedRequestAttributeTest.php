<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Routing\Attribute;

use InvalidArgumentException;
use Packio\Application\Middleware\ValidateMappedRequestMiddleware;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;

final class ValidateMappedRequestAttributeTest extends TestCase
{
    #[Test]
    public function itAggregatesValidationEntriesAndDeduplicatesTheMiddleware(): void
    {
        $validateMappedRequest = new ValidateMappedRequest(self::class);

        $defaults = [];
        $defaults = $validateMappedRequest->mergeDefaults($defaults);
        $defaults = new ValidateMappedRequest(self::class)->mergeDefaults($defaults);

        self::assertSame([
            [
                'dto' => self::class,
            ],
            [
                'dto' => self::class,
            ],
        ], $defaults['mapped_request_validations']);

        $middleware = $validateMappedRequest->getUniqueMiddleware();

        self::assertSame(
            [
                ValidateMappedRequest::MIDDLEWARE_KEY => ValidateMappedRequestMiddleware::class,
            ],
            $middleware,
        );
        self::assertSame([], $validateMappedRequest->getMiddleware());
        self::assertSame([], $validateMappedRequest->getDefaults());
    }

    #[Test]
    public function itRejectsBlankOrPaddedTargets(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ValidateMappedRequest(' ' . self::class . ' ');
    }

    #[Test]
    public function validationFailedRegistersNoMiddleware(): void
    {
        $validationFailed = new ValidationFailed(redirectTo: '/somewhere');

        self::assertSame([], $validationFailed->getMiddleware());
        self::assertSame([], $validationFailed->getUniqueMiddleware());
        self::assertSame([], $validationFailed->getDefaults());
    }

    #[Test]
    public function validationFailedAggregatesRepeatablePoliciesWithoutLastWriteWins(): void
    {
        $first  = new ValidationFailed(message: 'First failure.');
        $second = new ValidationFailed(redirectTo: '/other', message: 'Second failure.');

        $defaults = $first->mergeDefaults([]);
        $defaults = $second->mergeDefaults($defaults);

        self::assertCount(2, $defaults['validation_failed']);
        self::assertSame('First failure.', $defaults['validation_failed'][0]['message']);
        self::assertSame('/other', $defaults['validation_failed'][1]['redirect_to']);
        self::assertSame([
            'redirect_to' => '/other',
            'message'     => 'Second failure.',
            'notify'      => 'flash',
            'level'       => 'error',
            'json_status' => 422,
            'old_input'   => [],
        ], $defaults['validation_failed'][1]);
    }

    #[Test]
    public function validationFailedNeverAddsTheValidatorMiddleware(): void
    {
        $first  = new ValidationFailed();
        $second = new ValidationFailed(message: 'Other.');

        $defaults = $first->mergeDefaults([]);
        $defaults = $second->mergeDefaults($defaults);

        self::assertSame([], $first->getUniqueMiddleware());
        self::assertSame([], $second->getUniqueMiddleware());
        self::assertCount(2, $defaults['validation_failed']);
        self::assertNotContains(ValidateMappedRequestMiddleware::class, array_keys($first->getUniqueMiddleware()));
    }
}
