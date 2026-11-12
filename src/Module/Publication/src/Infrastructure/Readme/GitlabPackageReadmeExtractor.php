<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use Packio\Publication\Infrastructure\Gitlab\GitlabAccessCredentials;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function is_array;
use function is_string;
use function rawurlencode;
use function sprintf;
use function substr;
use function trim;

final readonly class GitlabPackageReadmeExtractor implements GitlabPackageReadmeExtractorInterface
{
    public function __construct(
        private UpstreamHttpClient $upstreamHttpClient,
        private BrandingConfig $brandingConfig,
        private ReadmeExtractorTemplate $readmeExtractorTemplate,
        private Base64FileContentDecoder $base64FileContentDecoder,
        private StoredSecretResolver $storedSecretResolver,
        private ?LoggerInterface $logger = null,
    ) {}

    public function extractGitlabReadmeHtml(
        string $namespace,
        string $repository,
        string $reference,
        ?GitlabAccessCredentials $gitlabAccessCredentials = null
    ): ?string {
        $this->log('debug', "Extracting readme for {$namespace}/{$repository}, ref: {$reference}");

        $html = $this->readmeExtractorTemplate->extract(
            fn (string $path): ?string => $this->fetchGitlabReadmeCandidate(
                $namespace,
                $repository,
                $path,
                $reference,
                $gitlabAccessCredentials,
            ),
            function(string $path): void {
                $this->log('debug', "No readme found at {$path}");
            },
            function(string $path): void {
                $this->log('info', "Found readme at {$path}, converting to HTML");
            },
        );

        if (null === $html) {
            $this->log('debug', 'No readme files found');
        }

        return $html;
    }

    private function log(string $level, string $message): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, '[GitLab Readme Extractor] ' . $message);
        }
    }

    private function fetchGitlabReadmeCandidate(
        string $namespace,
        string $repository,
        string $path,
        string $reference,
        ?GitlabAccessCredentials $gitlabAccessCredentials,
    ): ?string {
        $url = sprintf(
            'https://gitlab.com/api/v4/projects/%s/repository/files/%s?ref=%s',
            rawurlencode($namespace . '/' . $repository),
            rawurlencode($path),
            rawurlencode($reference),
        );

        return $this->fetchGitlabFile($url, $gitlabAccessCredentials);
    }

    private function fetchGitlabFile(string $url, ?GitlabAccessCredentials $gitlabAccessCredentials): ?string
    {
        $this->log('debug', 'Fetching: ' . $url);

        try {
            $response = $this->upstreamHttpClient->request('GET', $url, $this->httpHeaders($gitlabAccessCredentials));
        } catch (UpstreamHttpException) {
            throw new RuntimeException(sprintf('Failed to fetch GitLab README from "%s".', $url));
        }

        $this->log('debug', 'Response: ' . substr($response->body(), 0, 200));

        if ($response->isNotFound()) {
            $this->log('debug', 'File not found');

            return null;
        }

        if ($response->statusCode() >= 400) {
            throw new RuntimeException(sprintf('GitLab README API error (%d) while fetching "%s".', $response->statusCode(), $url));
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('GitLab README API returned invalid JSON while fetching "%s".', $url));
        }

        $encodedContent = $decoded['content'] ?? null;
        if (! is_string($encodedContent)) {
            throw new RuntimeException(sprintf('GitLab README API response does not contain file content for "%s".', $url));
        }

        $decoded = $this->base64FileContentDecoder->decode($encodedContent);
        if (false === $decoded) {
            throw new RuntimeException(sprintf('GitLab README API returned invalid base64 file content for "%s".', $url));
        }

        return trim($decoded);
    }

    /**
     * @return array<string, string>
     */
    private function httpHeaders(?GitlabAccessCredentials $gitlabAccessCredentials): array
    {
        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if ($gitlabAccessCredentials instanceof GitlabAccessCredentials && $gitlabAccessCredentials->hasToken()) {
            $token                    = $this->storedSecretResolver->resolve($gitlabAccessCredentials->token);
            if ('' === $token) {
                throw new RuntimeException('Stored provider credential could not be decrypted.');
            }
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }
}
