<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Request;

use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

use function is_string;

final readonly class SourceConnectRequest
{
    /** @var list<string> */
    public array $resourceUrls;

    /** @var array<string, string> */
    public array $resourceDefaultRefs;

    /**
     * @param list<string>|string          $resourceUrls
     * @param array<string, string>|string $resourceDefaultRefs
     */
    public function __construct(
        #[Required]
        #[Regex('#^(github|gitlab|gitlab_self_hosted|generic_git|bitbucket|codeberg)$#')]
        public string $provider = '',
        #[Regex('#^(public|private)$#')]
        public string $visibility = 'public',
        public string $authorizationToken = '',
        public string $authorizationId = '',
        public string $resourceUrl = '',
        public string $repositoryUrl = '',
        public string $instanceUrl = '',
        #[Regex('#^$|^[0-9a-fA-F-]{36}$#')]
        public string $providerInstanceId = '',
        public string $authScheme = '',
        public string $composerJsonPath = '',
        #[Regex('#^(0|[1-9]\d*)$#')]
        public string $keepLastReleases = '0',
        array|string $resourceUrls = [],
        array|string $resourceDefaultRefs = [],
    ) {
        $this->resourceUrls = is_string($resourceUrls)
            ? ('' === $resourceUrls ? [] : [$resourceUrls])
            : $resourceUrls;

        $this->resourceDefaultRefs = is_string($resourceDefaultRefs)
            ? []
            : $resourceDefaultRefs;
    }
}
