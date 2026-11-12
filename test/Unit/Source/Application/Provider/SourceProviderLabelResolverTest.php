<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Provider;

use BadMethodCallException;
use Packio\Source\Application\Provider\SourceProviderLabelResolver;
use Packio\Source\Application\Provider\SourceProviderRegistry;
use Packio\Source\Domain\Dto\ProviderDescriptor;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Provider\SourceProviderInterface;
use PackioTest\Support\Fake\FallbackAvailabilityCollaborators;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceProviderLabelResolver::class)]
final class SourceProviderLabelResolverTest extends TestCase
{
    use FallbackAvailabilityCollaborators;

    #[Test]
    public function labelOfDisabledProviderIsItsDescriptorLabel(): void
    {
        $provider = new readonly class implements SourceProviderInterface {
            public function descriptor(): ProviderDescriptor
            {
                return new ProviderDescriptor(name: 'github', label: 'GitHub', supportsAuthorization: true);
            }

            public function resources(ProviderAuthorization $providerAuthorization, ?string $accessToken = null): array
            {
                return [];
            }

            public function registerSource(
                string $organizationId,
                SourceRegistrationData $sourceRegistrationData,
                ?ProviderAuthorization $providerAuthorization = null,
            ): Source {
                throw new BadMethodCallException('Not needed in this test.');
            }

            public function generateWebhookSecret(): string
            {
                return '';
            }
        };

        $sourceProviderLabelResolver = new SourceProviderLabelResolver(new SourceProviderRegistry(
            [$provider],
            $this->availabilityResolver([
                'providers' => [
                    'github' => [
                        'enabled' => false,
                    ],
                ],
            ]),
        ));

        self::assertSame('GitHub', $sourceProviderLabelResolver->resolve('github'));
        self::assertSame('Foo Bar', $sourceProviderLabelResolver->resolve('foo_bar'));
    }
}
