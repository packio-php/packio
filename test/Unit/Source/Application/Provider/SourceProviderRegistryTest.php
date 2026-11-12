<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Provider;

use BadMethodCallException;
use InvalidArgumentException;
use Packio\Source\Application\Provider\SourceProviderRegistry;
use Packio\Source\Domain\Dto\ProviderDescriptor;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Provider\SourceProviderInterface;
use Packio\Source\Domain\ValueObject\SourceProvider;
use PackioTest\Support\Fake\FallbackAvailabilityCollaborators;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(SourceProviderRegistry::class)]
final class SourceProviderRegistryTest extends TestCase
{
    use FallbackAvailabilityCollaborators;

    public function testProvidersReturnsOnlyEnabledProviders(): void
    {
        $sourceProviderRegistry = new SourceProviderRegistry(
            [
                $this->provider(SourceProvider::Github->value),
                $this->provider(SourceProvider::Gitlab->value),
            ],
            $this->availabilityResolver([
                'providers' => [
                    SourceProvider::Github->value => [
                        'enabled' => false,
                    ],
                ],
            ]),
        );

        self::assertSame(
            [SourceProvider::Gitlab->value],
            array_map(
                static fn (SourceProviderInterface $sourceProvider): string => $sourceProvider->descriptor()->name,
                $sourceProviderRegistry->providers(),
            ),
        );
    }

    public function testDisabledProviderCannotBeResolvedDirectly(): void
    {
        $sourceProviderRegistry = new SourceProviderRegistry(
            [
                $this->provider(SourceProvider::Github->value),
            ],
            $this->availabilityResolver([
                'providers' => [
                    SourceProvider::Github->value => [
                        'enabled' => false,
                    ],
                ],
            ]),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source provider "github" is not registered.');

        $sourceProviderRegistry->provider(SourceProvider::Github->value);
    }

    private function provider(string $name): SourceProviderInterface
    {
        return new readonly class($name) implements SourceProviderInterface {
            public function __construct(private string $name) {}

            public function descriptor(): ProviderDescriptor
            {
                return new ProviderDescriptor(
                    name: $this->name,
                    label: $this->name,
                    supportsAuthorization: true,
                );
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
    }
}
