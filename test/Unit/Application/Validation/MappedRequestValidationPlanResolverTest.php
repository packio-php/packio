<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Validation;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Validation\InvalidMappedRequestValidationPlan;
use Packio\Application\Validation\MappedRequestValidationPlanResolver;
use Packio\Application\Validation\ValidationTargetPlanItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MappedRequestValidationPlanResolverTest extends TestCase
{
    private const string DTO_A = 'PackioTest\Unit\Application\Validation\PlanDtoA';

    private const string DTO_B = 'PackioTest\Unit\Application\Validation\PlanDtoB';

    private MappedRequestValidationPlanResolver $mappedRequestValidationPlanResolver;

    protected function setUp(): void
    {
        $this->mappedRequestValidationPlanResolver = new MappedRequestValidationPlanResolver();
    }

    #[Test]
    public function itBuildsAnOrderedPlanTargetingEachDto(): void
    {
        $items = $this->resolve([
            'valinor_mappings'           => [
                [
                    'route' => self::DTO_A,
                ],
                [
                    'body' => self::DTO_B,
                ],
            ],
            'mapped_request_validations' => [
                [
                    'dto' => self::DTO_A,
                ],
                [
                    'dto' => self::DTO_B,
                ],
            ],
        ]);

        self::assertCount(2, $items);
        self::assertSame(self::DTO_A, $items[0]->targetAttribute);
        self::assertSame(self::DTO_B, $items[1]->targetAttribute);
        self::assertTrue($items[0]->usesCanonicalDefaultPolicy());
        self::assertTrue($items[1]->usesCanonicalDefaultPolicy());
    }

    #[Test]
    #[DataProvider('mappingSourceKeys')]
    public function itMatchesTheDtoOfEveryMappingSourceKey(string $sourceKey): void
    {
        $items = $this->resolve([
            'valinor_mappings'           => [[
                $sourceKey => self::DTO_A,
            ]],
            'mapped_request_validations' => [[
                'dto' => self::DTO_A,
            ]],
        ]);

        self::assertCount(1, $items);
        self::assertSame(self::DTO_A, $items[0]->targetAttribute);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function mappingSourceKeys(): iterable
    {
        yield 'body' => ['body'];

        yield 'query' => ['query'];

        yield 'route' => ['route'];

        yield 'source' => ['source'];
    }

    #[Test]
    public function itAppliesTheSinglePolicyToEveryTarget(): void
    {
        $policy = [
            'message' => 'Invalid.',
        ];

        $items = $this->resolve([
            'valinor_mappings'           => [
                [
                    'body' => self::DTO_A,
                ],
                [
                    'body' => self::DTO_B,
                ],
            ],
            'mapped_request_validations' => [
                [
                    'dto' => self::DTO_A,
                ],
                [
                    'dto' => self::DTO_B,
                ],
            ],
            'validation_failed'          => [$policy],
        ]);

        self::assertSame($policy, $items[0]->options);
        self::assertSame($policy, $items[1]->options);
        self::assertFalse($items[0]->usesCanonicalDefaultPolicy());
    }

    #[Test]
    public function itFailsClosedOnADanglingValidationTarget(): void
    {
        $this->expectException(InvalidMappedRequestValidationPlan::class);

        $this->resolve([
            'valinor_mappings'           => [[
                'body' => self::DTO_A,
            ]],
            'mapped_request_validations' => [[
                'dto' => 'Vendor\Missing\Dto',
            ]],
        ]);
    }

    #[Test]
    public function itFailsClosedOnADuplicateValidationTarget(): void
    {
        $this->expectException(InvalidMappedRequestValidationPlan::class);

        $this->resolve([
            'valinor_mappings'           => [[
                'body' => self::DTO_A,
            ]],
            'mapped_request_validations' => [
                [
                    'dto' => self::DTO_A,
                ],
                [
                    'dto' => self::DTO_A,
                ],
            ],
        ]);
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    #[Test]
    #[DataProvider('malformedValidationEntries')]
    public function itFailsClosedOnAMalformedValidationEntry(array $entry): void
    {
        $this->expectException(InvalidMappedRequestValidationPlan::class);

        $this->resolve([
            'valinor_mappings'           => [[
                'body' => self::DTO_A,
            ]],
            'mapped_request_validations' => [$entry],
        ]);
    }

    /**
     * @return iterable<string, array{0: array<array-key, mixed>}>
     */
    public static function malformedValidationEntries(): iterable
    {
        yield 'missing dto' => [[]];

        yield 'empty dto' => [[
            'dto' => '',
        ]];

        yield 'non-string dto' => [[
            'dto' => 42,
        ]];
    }

    #[Test]
    public function itFailsClosedOnANonArrayValidationEntry(): void
    {
        $this->expectException(InvalidMappedRequestValidationPlan::class);

        $this->resolve([
            'valinor_mappings'           => [[
                'body' => self::DTO_A,
            ]],
            'mapped_request_validations' => ['not-an-array'],
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Test]
    #[DataProvider('missingMappings')]
    public function itFailsClosedWithoutMappings(array $options): void
    {
        $this->expectException(InvalidMappedRequestValidationPlan::class);

        $this->resolve([
            ...$options,
            'mapped_request_validations' => [[
                'dto' => self::DTO_A,
            ]],
        ]);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function missingMappings(): iterable
    {
        yield 'absent' => [[]];

        yield 'empty' => [[
            'valinor_mappings' => [],
        ]];
    }

    #[Test]
    public function itFailsClosedOnAMalformedPolicyEntry(): void
    {
        $this->expectException(InvalidMappedRequestValidationPlan::class);

        $this->resolve([
            'valinor_mappings'           => [[
                'body' => self::DTO_A,
            ]],
            'mapped_request_validations' => [[
                'dto' => self::DTO_A,
            ]],
            'validation_failed'          => ['not-an-array'],
        ]);
    }

    #[Test]
    public function itFailsClosedOnMultiplePolicies(): void
    {
        $this->expectException(InvalidMappedRequestValidationPlan::class);

        $this->resolve([
            'valinor_mappings'           => [[
                'body' => self::DTO_A,
            ]],
            'mapped_request_validations' => [[
                'dto' => self::DTO_A,
            ]],
            'validation_failed'          => [
                [
                    'message' => 'First.',
                ],
                [
                    'message' => 'Second.',
                ],
            ],
        ]);
    }

    #[Test]
    public function itReturnsAnEmptyPlanWithoutARouteResult(): void
    {
        self::assertSame([], $this->mappedRequestValidationPlanResolver->resolve(new ServerRequest([], [], '/examples', 'POST')));
    }

    #[Test]
    #[DataProvider('invalidRootMetadataValues')]
    public function itFailsClosedOnAWrongRootValidationEntriesMetadata(mixed $invalidValue): void
    {
        $this->expectException(InvalidMappedRequestValidationPlan::class);

        $this->resolve([
            'valinor_mappings'           => [[
                'body' => self::DTO_A,
            ]],
            'mapped_request_validations' => $invalidValue,
        ]);
    }

    #[Test]
    #[DataProvider('invalidRootMetadataValues')]
    public function itFailsClosedOnAWrongRootValidationFailedMetadata(mixed $invalidValue): void
    {
        $this->expectException(InvalidMappedRequestValidationPlan::class);

        $this->resolve([
            'valinor_mappings'           => [[
                'body' => self::DTO_A,
            ]],
            'mapped_request_validations' => [[
                'dto' => self::DTO_A,
            ]],
            'validation_failed'          => $invalidValue,
        ]);
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function invalidRootMetadataValues(): iterable
    {
        yield 'null' => [null];

        yield 'string' => ['bad'];

        yield 'true' => [true];

        yield 'integer' => [42];
    }

    #[Test]
    public function itReturnsAnEmptyPlanWithoutValidationMetadata(): void
    {
        self::assertSame([], $this->resolve([
            'valinor_mappings' => [[
                'body' => self::DTO_A,
            ]],
        ]));
    }

    #[Test]
    public function itReturnsAnEmptyPlanForAnEmptyValidationList(): void
    {
        self::assertSame([], $this->resolve([
            'valinor_mappings'           => [[
                'body' => self::DTO_A,
            ]],
            'mapped_request_validations' => [],
        ]));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<ValidationTargetPlanItem>
     */
    private function resolve(array $options): array
    {
        return $this->mappedRequestValidationPlanResolver->resolveFromOptions($options);
    }
}
