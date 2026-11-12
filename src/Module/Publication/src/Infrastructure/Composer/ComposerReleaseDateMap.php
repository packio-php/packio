<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Composer;

use DateTimeImmutable;
use DateTimeZone;

use function array_filter;
use function array_values;
use function ltrim;
use function sprintf;

final readonly class ComposerReleaseDateMap
{
    /**
     * @param array<string, string> $dates
     */
    public function __construct(private array $dates, private DateTimeImmutable $fallbackReleasedAt) {}

    public static function fallback(string $releasedAt): self
    {
        return new self([], new DateTimeImmutable($releasedAt));
    }

    public function find(string $version, string $reference): string
    {
        $keys = array_values(array_filter([
            sprintf('%s:%s', $version, $reference),
            sprintf('%s:%s', ltrim($version, 'v'), $reference),
            $version,
            ltrim($version, 'v'),
            $reference,
        ]));

        foreach ($keys as $key) {
            if (isset($this->dates[$key])) {
                return $this->dates[$key];
            }
        }

        return $this->format($this->fallbackReleasedAt);
    }

    private function format(DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
    }
}
