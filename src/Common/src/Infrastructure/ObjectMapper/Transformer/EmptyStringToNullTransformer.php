<?php

declare(strict_types=1);

namespace Common\Infrastructure\ObjectMapper\Transformer;

use Sirix\ObjectMapper\Contract\ValueTransformerInterface;

final class EmptyStringToNullTransformer implements ValueTransformerInterface
{
    public function transform(string $value): ?string
    {
        return '' === $value ? null : $value;
    }
}
