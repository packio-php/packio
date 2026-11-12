<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure;

use Common\Branding\BrandingConfig;
use InvalidArgumentException;
use Packio\Registry\Application\Service\DistArchiveFetcherInterface;

use function is_string;
use function rawurlencode;
use function sprintf;
use function trim;

final readonly class DistArchiveFetcher implements DistArchiveFetcherInterface
{
    public function __construct(
        private BrandingConfig $brandingConfig,
        private GitlabDistArchiveFetcher $gitlabDistArchiveFetcher,
        private GitlabSelfHostedDistArchiveFetcher $gitlabSelfHostedDistArchiveFetcher,
        private BitbucketDistArchiveFetcher $bitbucketDistArchiveFetcher,
        private CodebergDistArchiveFetcher $codebergDistArchiveFetcher,
        private GenericGitDistArchiveFetcher $genericGitDistArchiveFetcher,
        private HttpDistArchiveDownloader $httpDistArchiveDownloader,
    ) {}

    /**
     * @param array<string, mixed> $providerConfig
     */
    public function fetchArchive(
        string $provider,
        string $owner,
        string $repository,
        string $reference,
        ?string $token = null,
        string $baseUrl = '',
        array $providerConfig = [],
    ): DistArchiveStream {
        return match ($provider) {
            'github'             => $this->fetchGithubZipball(
                $owner,
                $repository,
                $reference,
                $token,
            ),
            'gitlab'             => $this->gitlabDistArchiveFetcher->fetchArchive(
                $owner,
                $repository,
                $reference,
                $token,
                (string) ($providerConfig['auth_strategy'] ?? ''),
            ),
            'gitlab_self_hosted' => $this->gitlabSelfHostedDistArchiveFetcher->fetchArchive(
                $baseUrl,
                $owner,
                $repository,
                $reference,
                $token,
                (string) ($providerConfig['auth_strategy'] ?? ''),
            ),
            'bitbucket'          => $this->bitbucketDistArchiveFetcher->fetchArchive(
                $owner,
                $repository,
                $reference,
                $token,
            ),
            'codeberg'           => $this->codebergDistArchiveFetcher->fetchArchive(
                $owner,
                $repository,
                $reference,
                $token,
            ),
            'generic_git'        => $this->genericGitDistArchiveFetcher->fetchArchive(
                $repository,
                $reference,
                $token,
                $providerConfig,
            ),
            default              => throw new InvalidArgumentException(
                sprintf('Archive fetch for provider "%s" is not supported.', $provider),
            ),
        };
    }

    private function fetchGithubZipball(string $owner, string $repository, string $reference, ?string $token = null): DistArchiveStream
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/zipball/%s',
            rawurlencode($owner),
            rawurlencode($repository),
            rawurlencode($reference),
        );

        $headers = [
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
            'Accept'     => 'application/vnd.github+json',
        ];

        if (is_string($token) && '' !== trim($token)) {
            $headers['Authorization'] = 'Bearer ' . trim($token);
        }

        return $this->httpDistArchiveDownloader->downloadZip($url, $headers, $repository . '-' . $reference . '.zip');
    }
}
