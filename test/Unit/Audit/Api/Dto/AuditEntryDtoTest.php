<?php

declare(strict_types=1);

namespace PackioTest\Unit\Audit\Api\Dto;

use InvalidArgumentException;
use Packio\Audit\Api\Dto\AuditEntryDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuditEntryDtoTest extends TestCase
{
    #[Test]
    public function itRequiresAValidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuditEntryDto('', 'source.sync', '2026-07-04T10:52:27+00:00', []);
    }
}
