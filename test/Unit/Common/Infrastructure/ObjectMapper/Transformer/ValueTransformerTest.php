<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\ObjectMapper\Transformer;

use Cake\Chronos\Chronos;
use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToNullableAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ValueTransformerTest extends TestCase
{
    #[Test]
    public function itTransformsUuidToItsCanonicalString(): void
    {
        self::assertSame(
            'f4f98980-2d12-4db6-89ba-75274400d5c1',
            new UuidInterfaceToStringTransformer()->transform(Uuid::fromString('f4f98980-2d12-4db6-89ba-75274400d5c1')),
        );
    }

    #[Test]
    public function itTransformsChronosValuesUsingAtomFormat(): void
    {
        $chronos = Chronos::parse('2026-08-29T12:34:56+03:00');

        self::assertSame('2026-08-29T12:34:56+03:00', new ChronosToAtomStringTransformer()->transform($chronos));
        self::assertSame('2026-08-29T12:34:56+03:00', new NullableChronosToAtomStringTransformer()->transform($chronos));
        self::assertSame('2026-08-29T12:34:56+03:00', new NullableChronosToNullableAtomStringTransformer()->transform($chronos));
    }

    #[Test]
    public function itPreservesTheDistinctLegacyNullableContracts(): void
    {
        self::assertSame('', new NullableChronosToAtomStringTransformer()->transform(null));
        self::assertNull(new NullableChronosToNullableAtomStringTransformer()->transform(null));
    }
}
