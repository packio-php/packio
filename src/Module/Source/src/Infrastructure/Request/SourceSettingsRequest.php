<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Request;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Packio\Source\Api\Dto\SourceSettingsInput;

final readonly class SourceSettingsRequest extends SourceSettingsInput
{
    /** @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $payload */
    public function __construct(#[FromBody(asRoot: true)] array $payload = [])
    {
        parent::__construct($payload);
    }
}
