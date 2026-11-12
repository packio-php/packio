<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Source\Api\Policy\SourcePolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Api\SourceSecretResolverInterface;
use Packio\Source\Application\Provider\SourceProviderLabelResolver;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Infrastructure\Handler\SourceDetailsHandler;
use Packio\Source\Infrastructure\Page\SourceDetailsPageDataBuilder;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceDetailsHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceDetailsHandlerValidationTest extends TestCase
{
    #[Test]
    public function invalidSourceIdRedirectsWithoutQueryingFacade(): void
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->expects(self::never())->method('findSource');

        $sourceDetailsPageDataBuilder = new SourceDetailsPageDataBuilder(
            $this->createMock(PackageApiFacadeInterface::class),
            $this->createMock(ReleaseApiFacadeInterface::class),
            $sourceApiFacade,
            $this->createMock(SourceSecretResolverInterface::class),
            $this->createMock(WebhookApiFacadeInterface::class),
            $this->createMock(AuditApiFacadeInterface::class),
            new OrganizationPolicy(),
            new BrandingConfig(),
            new SourceProviderLabelResolver($this->createMock(SourceProviderRegistryInterface::class)),
        );

        $sourceDetailsHandler = new SourceDetailsHandler(
            $sourceApiFacade,
            new SourcePolicy(),
            $sourceDetailsPageDataBuilder,
        );

        $serverRequest = new ServerRequest([], [], '/organization/my-org/sources/01ag049df-826b-7052-b6f1-efeec4d10618', 'GET')
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-1', 'my-org', 'My Org'))
            ->withAttribute('sourceId', '01ag049df-826b-7052-b6f1-efeec4d10618')
        ;

        $response = $sourceDetailsHandler->handle($serverRequest);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/my-org/sources', $response->getHeaderLine('Location'));
    }
}
