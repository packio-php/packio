<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationOutcome;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationResult;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Handler\OrganizationSettingsDeleteHandler;
use Packio\Organization\Infrastructure\Request\OrganizationDeleteRequest;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Domain\Organization\ActorContext;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;

use function in_array;

final class OrganizationSettingsDeleteHandlerTest extends TestCase
{
    #[DataProvider('outcomes')]
    public function testMapsOutcome(OrganizationDeletionInitiationOutcome $organizationDeletionInitiationOutcome): void
    {
        $facade       = $this->createMock(OrganizationApiFacadeInterface::class);
        $actorContext = new ActorContext('org', 'actor', 'owner');
        $facade->expects(self::once())->method('deleteOrganizationInContextWithOutcome')->with($actorContext)->willReturn(new OrganizationDeletionInitiationResult($organizationDeletionInitiationOutcome));
        $testInertia   = new TestInertia();
        $serverRequest = new ServerRequest()->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org', 'org', 'Org'))
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
            ->withAttribute(OrganizationDeleteRequest::class, new OrganizationDeleteRequest('Org'))
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
        ;
        $response = new OrganizationSettingsDeleteHandler($facade, new InertiaNotificationMessenger())->handle($serverRequest);
        self::assertSame('/organizations', $response->getHeaderLine('Location'));
        $success = in_array($organizationDeletionInitiationOutcome, [OrganizationDeletionInitiationOutcome::Initiated, OrganizationDeletionInitiationOutcome::AlreadyInProgress], true);
        self::assertSame($success ? [
            'success' => 'Organization will be permanently deleted.',
        ] : [
            'error' => 'Organization not found.',
        ], $testInertia->flash);
    }

    /** @return iterable<string, array{OrganizationDeletionInitiationOutcome}> */
    public static function outcomes(): iterable
    {
        foreach (OrganizationDeletionInitiationOutcome::cases() as $outcome) {
            yield $outcome->value => [$outcome];
        }
    }
}
