<?php

declare(strict_types=1);

namespace Common\Infrastructure\ObjectMapper\Transformer;

use Ramsey\Uuid\UuidInterface;
use Sirix\ObjectMapper\Contract\ValueTransformerInterface;

final class UuidInterfaceToStringTransformer implements ValueTransformerInterface
{
    public function transform(UuidInterface $value): string
    {
        return $value->toString();
    }
}
