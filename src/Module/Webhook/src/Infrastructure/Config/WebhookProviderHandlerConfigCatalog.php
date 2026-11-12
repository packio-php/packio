<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Config;

use InvalidArgumentException;
use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerConfig;
use Sirix\ContainerResolver\ConfigReader;

use function is_string;
use function sprintf;

final readonly class WebhookProviderHandlerConfigCatalog
{
    private const array DEFAULT_GITHUB_EVENTS    = ['push', 'create', 'release'];
    private const array DEFAULT_GITLAB_EVENTS    = ['Push Hook', 'Tag Push Hook'];
    private const array DEFAULT_BITBUCKET_EVENTS = ['repo:push'];
    private const array DEFAULT_CODEBERG_EVENTS  = ['push', 'create', 'delete'];

    private function __construct(private ConfigReader $configReader) {}

    public static function fromConfigReader(ConfigReader $configReader): self
    {
        return new self($configReader);
    }

    public function github(): WebhookProviderHandlerConfig
    {
        return new WebhookProviderHandlerConfig(
            provider: 'github',
            events: $this->events('github', self::DEFAULT_GITHUB_EVENTS),
            signatureHeader: $this->configReader->string('github.signature_header', 'X-Hub-Signature-256'),
        );
    }

    public function gitlab(): WebhookProviderHandlerConfig
    {
        return new WebhookProviderHandlerConfig(
            provider: 'gitlab',
            events: $this->events('gitlab', self::DEFAULT_GITLAB_EVENTS),
        );
    }

    public function gitlabSelfHosted(): WebhookProviderHandlerConfig
    {
        // GitLab.com and self-hosted GitLab use the same incoming webhook event names.
        return new WebhookProviderHandlerConfig(
            provider: 'gitlab_self_hosted',
            events: $this->events('gitlab', self::DEFAULT_GITLAB_EVENTS),
        );
    }

    public function bitbucket(): WebhookProviderHandlerConfig
    {
        return new WebhookProviderHandlerConfig(
            provider: 'bitbucket',
            events: $this->events('bitbucket', self::DEFAULT_BITBUCKET_EVENTS),
        );
    }

    public function codeberg(): WebhookProviderHandlerConfig
    {
        return new WebhookProviderHandlerConfig(
            provider: 'codeberg',
            events: $this->events('codeberg', self::DEFAULT_CODEBERG_EVENTS),
        );
    }

    /**
     * @param list<string> $default
     *
     * @return list<string>
     */
    private function events(string $provider, array $default): array
    {
        $path = $provider . '.events';

        return $this->stringList($this->configReader->list($path, $default), $path);
    }

    /**
     * @param list<mixed> $values
     *
     * @return list<string>
     */
    private function stringList(array $values, string $path): array
    {
        $strings = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new InvalidArgumentException(sprintf('Webhook provider handler config "%s" must be a list of strings.', $path));
            }

            $strings[] = $value;
        }

        return $strings;
    }
}
