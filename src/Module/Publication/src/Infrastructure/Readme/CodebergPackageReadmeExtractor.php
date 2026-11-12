<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use Packio\Publication\Infrastructure\Codeberg\CodebergAccessCredentials;
use RuntimeException;

use function is_array;
use function is_string;
use function rawurlencode;
use function sprintf;
use function trim;

final readonly class CodebergPackageReadmeExtractor implements CodebergPackageReadmeExtractorInterface
{
    public function __construct(
        private UpstreamHttpClient $upstreamHttpClient,
        private BrandingConfig $brandingConfig,
        private ReadmeExtractorTemplate $readmeExtractorTemplate,
        private Base64FileContentDecoder $base64FileContentDecoder,
        private StoredSecretResolver $storedSecretResolver,
    ) {}

    public function extractCodebergReadmeHtml(
        string $owner,
        string $repository,
        string $reference,
        ?CodebergAccessCredentials $codebergAccessCredentials = null
    ): ?string {
        return $this->readmeExtractorTemplate->extract(
            fn (string $path): ?string => $this->fetchCodebergReadmeCandidate(
                $owner,
                $repository,
                $path,
                $reference,
                $codebergAccessCredentials,
            ),
        );
    }

    private function fetchCodebergReadmeCandidate(
        string $owner,
        string $repository,
        string $path,
        string $reference,
        ?CodebergAccessCredentials $codebergAccessCredentials,
    ): ?string {
        $url = sprintf(
            'https://codeberg.org/api/v1/repos/%s/%s/contents/%s?ref=%s',
            rawurlencode($owner),
            rawurlencode($repository),
            rawurlencode($path),
            rawurlencode($reference),
        );

        foreach ([false, true] as $manualToken) {
            try {
                $response = $this->upstreamHttpClient->request('GET', $url, $this->httpHeaders($codebergAccessCredentials, $manualToken));
            } catch (UpstreamHttpException) {
                continue;
            }

            if ($response->statusCode() >= 400) {
                continue;
            }

            $decodedContent = $this->decodeContentsResponse($response->json());
            if (null !== $decodedContent && '' !== trim($decodedContent)) {
                return trim($decodedContent);
            }
        }

        return null;
    }

    private function decodeContentsResponse(mixed $decoded): ?string
    {
        if (! is_array($decoded)) {
            return null;
        }

        $encodedContent = $decoded['content'] ?? null;
        if (! is_string($encodedContent) || '' === trim($encodedContent)) {
            return null;
        }

        $content = $this->base64FileContentDecoder->decode($encodedContent);

        return false === $content ? null : $content;
    }

    /**
     * @return array<string, string>
     */
    private function httpHeaders(?CodebergAccessCredentials $codebergAccessCredentials, bool $manualToken): array
    {
        $headers = [
            'Accept'     => 'text/plain',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if ($codebergAccessCredentials instanceof CodebergAccessCredentials && $codebergAccessCredentials->hasToken()) {
            $token                    = $this->storedSecretResolver->resolve($codebergAccessCredentials->token);
            if ('' === trim($token)) {
                throw new RuntimeException('Stored provider credential could not be decrypted.');
            }
            $headers['Authorization'] = ($manualToken ? 'token ' : 'Bearer ') . trim($token);
        }

        return $headers;
    }
}
