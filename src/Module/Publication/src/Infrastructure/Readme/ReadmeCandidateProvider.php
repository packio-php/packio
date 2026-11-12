<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

final readonly class ReadmeCandidateProvider
{
    /**
     * @return list<string>
     */
    public function candidates(): array
    {
        return [
            'README.md',
            'readme.md',
            'README.markdown',
            'README',
        ];
    }
}
