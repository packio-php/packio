<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Provider;

use Packio\Source\Application\Port\SourceProviderAvailabilityOverridePort;
use Packio\Source\Application\Provider\SourceProviderAvailabilityResolver;
use Packio\Source\Domain\ValueObject\SourceProvider;
use PackioTest\Support\Fake\FallbackAvailabilityCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceProviderAvailabilityResolver::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceProviderAvailabilityResolverTest extends TestCase
{
    use FallbackAvailabilityCollaborators;

    public function testProviderIsEnabledByDefault(): void
    {
        $sourceProviderAvailabilityResolver = $this->availabilityResolver([]);

        self::assertTrue($sourceProviderAvailabilityResolver->isEnabled(SourceProvider::Github));
        self::assertTrue($sourceProviderAvailabilityResolver->isEnabled('gitlab'));
    }

    public function testProviderCanBeDisabledByConfig(): void
    {
        $sourceProviderAvailabilityResolver = $this->availabilityResolver([
            'providers' => [
                SourceProvider::Github->value => [
                    'enabled' => false,
                ],
                SourceProvider::Gitlab->value => [
                    'enabled' => true,
                ],
            ],
        ]);

        self::assertFalse($sourceProviderAvailabilityResolver->isEnabled(SourceProvider::Github));
        self::assertTrue($sourceProviderAvailabilityResolver->isEnabled(SourceProvider::Gitlab));
    }

    public function testProviderCanBeDisabledByRuntimeOverride(): void
    {
        $overridePort = $this->createStub(SourceProviderAvailabilityOverridePort::class);
        $overridePort
            ->method('isProviderEnabled')
            ->willReturnCallback(static fn (string $providerKey, bool $fallback): bool => match ($providerKey) {
                SourceProvider::Github->value => false,
                default                       => $fallback,
            })
        ;

        $sourceProviderAvailabilityResolver = new SourceProviderAvailabilityResolver([
            'providers' => [
                SourceProvider::Github->value => [
                    'enabled' => true,
                ],
            ],
        ], $overridePort);

        self::assertFalse($sourceProviderAvailabilityResolver->isEnabled(SourceProvider::Github));
    }
}
