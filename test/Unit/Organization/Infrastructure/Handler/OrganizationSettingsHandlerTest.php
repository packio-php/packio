<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Infrastructure\Handler;

use Common\Provider\SourceProviderMetadataCatalog;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Handler\OrganizationSettingsHandler;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Source\Api\Dto\SourceProviderDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;

#[CoversClass(OrganizationSettingsHandler::class)]
final class OrganizationSettingsHandlerTest extends TestCase
{
    #[Test]
    public function passesAvailableSelfHostedProvidersToThePage(): void
    {
        $props = $this->renderProps([
            new SourceProviderDto('github', 'GitHub', ''),
            new SourceProviderDto('gitlab_self_hosted', 'GitLab Self-Hosted', ''),
            new SourceProviderDto('forgejo_self_hosted', 'Forgejo Self-Hosted', ''),
        ]);

        self::assertTrue($props['source_provider_instances_enabled']);
        self::assertSame([
            [
                'key'   => 'gitlab_self_hosted',
                'label' => 'GitLab Self-Hosted',
            ],
            [
                'key'   => 'forgejo_self_hosted',
                'label' => 'Forgejo Self-Hosted',
            ],
        ], $props['self_hosted_source_providers']);
    }

    #[Test]
    public function enablesInstancesForForgejoAloneAndDisablesWithoutSelfHosted(): void
    {
        $forgejoOnly = $this->renderProps([new SourceProviderDto('forgejo_self_hosted', 'Forgejo Self-Hosted', '')]);
        self::assertTrue($forgejoOnly['source_provider_instances_enabled']);

        $none = $this->renderProps([new SourceProviderDto('github', 'GitHub', '')]);
        self::assertFalse($none['source_provider_instances_enabled']);
        self::assertSame([], $none['self_hosted_source_providers']);
    }

    /**
     * @param list<SourceProviderDto> $providers
     *
     * @return array<string, mixed>
     */
    private function renderProps(array $providers): array
    {
        $organizationApiFacade = $this->createStub(OrganizationApiFacadeInterface::class);
        $sourceApiFacade       = $this->createStub(SourceApiFacadeInterface::class);
        $sourceApiFacade->method('availableProviders')->willReturn($providers);

        $captured = [];
        $inertia  = $this->createStub(InertiaInterface::class);
        $inertia->method('render')->willReturnCallback(static function(string $component, array $props = []) use (&$captured): EmptyResponse {
            $captured = $props;

            return new EmptyResponse();
        });

        $request = new ServerRequest()
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia)
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-1', 'my-org', 'My Org'))
        ;

        new OrganizationSettingsHandler($organizationApiFacade, $sourceApiFacade, new SourceProviderMetadataCatalog())->handle($request);

        return $captured;
    }
}
