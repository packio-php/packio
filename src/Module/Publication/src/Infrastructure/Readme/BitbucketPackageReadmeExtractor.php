<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use Packio\Publication\Infrastructure\Bitbucket\BitbucketAccessCredentials;
use RuntimeException;

use function rawurlencode;
use function sprintf;
use function trim;

final readonly class BitbucketPackageReadmeExtractor implements BitbucketPackageReadmeExtractorInterface
{
    public function __construct(
        private UpstreamHttpClient $upstreamHttpClient,
        private BrandingConfig $brandingConfig,
        private ReadmeExtractorTemplate $readmeExtractorTemplate,
        private StoredSecretResolver $storedSecretResolver,
    ) {}

    public function extractBitbucketReadmeHtml(
        string $workspace,
        string $repository,
        string $reference,
        ?BitbucketAccessCredentials $bitbucketAccessCredentials = null
    ): ?string {
        return $this->readmeExtractorTemplate->extract(
            fn (string $path): ?string => $this->fetchBitbucketReadmeCandidate(
                $workspace,
                $repository,
                $path,
                $reference,
                $bitbucketAccessCredentials,
            ),
        );
    }

    private function fetchBitbucketReadmeCandidate(
        string $workspace,
        string $repository,
        string $path,
        string $reference,
        ?BitbucketAccessCredentials $bitbucketAccessCredentials,
    ): ?string {
        $url = sprintf(
            'https://api.bitbucket.org/2.0/repositories/%s/%s/src/%s/%s',
            rawurlencode($workspace),
            rawurlencode($repository),
            rawurlencode($reference),
            rawurlencode($path),
        );

        try {
            $response = $this->upstreamHttpClient->request('GET', $url, $this->httpHeaders($bitbucketAccessCredentials));
        } catch (UpstreamHttpException) {
            return null;
        }

        if ($response->statusCode() >= 400 || '' === trim($response->body())) {
            return null;
        }

        return trim($response->body());
    }

    /**
     * @return array<string, string>
     */
    private function httpHeaders(?BitbucketAccessCredentials $bitbucketAccessCredentials): array
    {
        $headers = [
            'Accept'     => 'text/plain',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if ($bitbucketAccessCredentials instanceof BitbucketAccessCredentials && $bitbucketAccessCredentials->hasToken()) {
            $token                    = $this->storedSecretResolver->resolve($bitbucketAccessCredentials->token);
            if ('' === trim($token)) {
                throw new RuntimeException('Stored provider credential could not be decrypted.');
            }
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }
}
