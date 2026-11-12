<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Config;

use Packio\Organization\Domain\Entity\Organization;

use function array_values;
use function in_array;
use function is_string;

final readonly class ReservedOrganizationSlugs
{
    public const array DEFAULT_SLUGS = [
        'admin',
        'api',
        'app',
        'composer',
        'registry',
        'www',
    ];

    /** @var list<string> */
    private array $slugs;

    /**
     * @param array<array-key, mixed> $reservedSlugs
     */
    public function __construct(array $reservedSlugs)
    {
        $this->slugs = $this->normalizedList($reservedSlugs);
    }

    public function contains(string $slug): bool
    {
        $normalized = Organization::normalizeSlug($slug);

        return '' !== $normalized && in_array($normalized, $this->slugs, true);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->slugs;
    }

    /**
     * @param array<array-key, mixed> $slugs
     *
     * @return list<string>
     */
    private function normalizedList(array $slugs): array
    {
        $normalizedSlugs = [];
        foreach ($slugs as $slug) {
            if (! is_string($slug)) {
                continue;
            }

            $normalized = Organization::normalizeSlug($slug);
            if ('' !== $normalized) {
                $normalizedSlugs[$normalized] = $normalized;
            }
        }

        return array_values($normalizedSlugs);
    }
}
