<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Worker\Api\Dto\JobLogDto;

final readonly class JobLogResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(JobLogDto $jobLogDto): array
    {
        return [
            'id'         => $jobLogDto->id,
            'job_id'     => $jobLogDto->jobId,
            'level'      => $jobLogDto->level,
            'message'    => $jobLogDto->message,
            'context'    => $jobLogDto->context,
            'created_at' => $jobLogDto->createdAt,
        ];
    }
}
