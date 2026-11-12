<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Webhook;

use Common\Branding\BrandingConfig;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SecretResolverInterface;

final readonly class WebhookRegistrationTemplateFactory
{
    public function __construct(
        private BrandingConfig $brandingConfig,
        private SecretResolverInterface $secretResolver,
        private WebhookHttpRequestor $webhookHttpRequestor,
        private SourceWebhookSecrets $sourceWebhookSecrets,
    ) {}

    public function create(WebhookProviderAdapterInterface $webhookProviderAdapter): WebhookRegistrationTemplate
    {
        return new WebhookRegistrationTemplate(
            $this->brandingConfig,
            $this->secretResolver,
            $this->webhookHttpRequestor,
            $webhookProviderAdapter,
            $this->sourceWebhookSecrets,
        );
    }
}
