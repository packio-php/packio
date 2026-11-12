<?php

declare(strict_types=1);

namespace Common\Infrastructure\ObjectMapper\Transformer;

use Cake\Chronos\Chronos;
use Sirix\ObjectMapper\Contract\ValueTransformerInterface;

final class NullableChronosToAtomStringTransformer implements ValueTransformerInterface
{
    public function transform(?Chronos $value): string
    {
        return $value?->format(DATE_ATOM) ?? '';
    }
}
