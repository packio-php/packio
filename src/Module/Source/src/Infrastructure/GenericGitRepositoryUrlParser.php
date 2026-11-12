<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure;

use Common\Infrastructure\HttpClient\OutboundUrlPolicy;
use InvalidArgumentException;

use function array_pop;
use function explode;
use function in_array;
use function parse_url;
use function preg_match;
use function preg_replace;
use function rawurldecode;
use function sprintf;
use function trim;

final readonly class GenericGitRepositoryUrlParser
{
    private const array BLOCKED_HOSTS = [
        'github.com',
        'gitlab.com',
    ];

    public function __construct(private OutboundUrlPolicy $outboundUrlPolicy = new OutboundUrlPolicy()) {}

    /**
     * @return array{clone_url: string, normalized_url: string, display_name: string, repository: string}
     */
    public function parse(string $url): array
    {
        $normalized = (string) preg_replace('#\.git$#', '', trim($url));

        $target = $this->outboundUrlPolicy->parse($normalized);
        $parts  = parse_url($normalized);
        if (isset($parts['query']) || ! isset($parts['path']) || '/' === $parts['path']) {
            throw new InvalidArgumentException('Invalid generic Git repository URL.');
        }
        $host = $target['host'];
        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The host "%s" is not supported for generic Git. Please use the dedicated provider instead.',
                    $host,
                ),
            );
        }

        $path       = trim($parts['path'], '/');
        foreach (explode('/', $path) as $segment) {
            $decoded = rawurldecode($segment);
            if ('' === $decoded || '.' === $decoded || '..' === $decoded || preg_match('/[\\\\\/\x00-\x20\x7f?#]/', $decoded)) {
                throw new InvalidArgumentException('Invalid generic Git repository path.');
            }
        }
        $normalized = $target['origin'] . '/' . $path;
        $segments   = explode('/', $path);
        $repository = array_pop($segments);

        return [
            'clone_url'      => $normalized,
            'normalized_url' => $normalized,
            'display_name'   => $repository,
            'repository'     => $repository,
        ];
    }
}
