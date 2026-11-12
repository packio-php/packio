<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Git;

use function array_values;
use function is_string;

final readonly class GitReference
{
    public const string TYPE_BRANCH = 'branch';
    public const string TYPE_TAG    = 'tag';

    public function __construct(
        public string $name,
        public string $commit,
        public string $type,
    ) {}

    /**
     * @param list<self|string> $references
     *
     * @return list<self>
     */
    public static function uniqueByName(array $references): array
    {
        $unique = [];
        foreach ($references as $reference) {
            $reference = self::fromMixed($reference);
            if (! $reference instanceof self) {
                continue;
            }

            $unique[$reference->name] ??= $reference;
        }

        return array_values($unique);
    }

    /**
     * @param list<self|string> $references
     */
    public static function resolveByName(array $references, string $name, string $fallbackType = self::TYPE_BRANCH): self
    {
        foreach ($references as $reference) {
            $reference = self::fromMixed($reference);
            if (! $reference instanceof self) {
                continue;
            }

            if ($reference->name === $name) {
                return $reference;
            }
        }

        return new self($name, $name, $fallbackType);
    }

    private static function fromMixed(mixed $reference): ?self
    {
        if ($reference instanceof self) {
            return $reference;
        }

        if (is_string($reference) && '' !== $reference) {
            return new self($reference, $reference, self::TYPE_BRANCH);
        }

        return null;
    }
}
