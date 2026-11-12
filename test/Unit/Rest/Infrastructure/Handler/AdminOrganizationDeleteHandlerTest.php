<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Api\Dto\OrganizationDeletionContext;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationOutcome as Outcome;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationResult as Result;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Rest\Infrastructure\Handler\AdminOrganizationDeleteHandler;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function in_array;

final class AdminOrganizationDeleteHandlerTest extends TestCase
{
    #[DataProvider('outcomes')]
    public function testMapsOutcome(Outcome $outcome, int $status): void
    {
        $facade = $this->createMock(OrganizationApiFacadeInterface::class);
        $facade->expects(self::once())->method('initiateOrganizationDeletion')
            ->with(self::callback(static fn (OrganizationDeletionContext $organizationDeletionContext): bool => '0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d' === $organizationDeletionContext->organizationId && 'actor' === $organizationDeletionContext->actorId && 'api' === $organizationDeletionContext->origin && 'organization.delete.initiated' === $organizationDeletionContext->auditEvent))
            ->willReturn(new Result($outcome))
        ;
        $adminOrganizationDeleteHandler  = new AdminOrganizationDeleteHandler($facade, new ApiResponseFactory(), new ApiErrorResponseFactory());
        $response                        = $adminOrganizationDeleteHandler->handle(new ServerRequest()->withAttribute('organizationId', ' 0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d ')->withAttribute(RequestContextAttributes::CURRENT_USER_ID, 'actor'));
        self::assertSame($status, $response->getStatusCode());
        if (404 === $status) {
            self::assertStringContainsString('Organization not found.', (string) $response->getBody());
        }
    }

    /** @return iterable<string, array{Outcome, int}> */
    public static function outcomes(): iterable
    {
        foreach (Outcome::cases() as $outcome) {
            yield $outcome->value => [$outcome, in_array($outcome, [Outcome::Initiated, Outcome::AlreadyInProgress], true) ? 204 : 404];
        }
    }

    public function testMissingIdDoesNotInitiate(): void
    {
        $facade = $this->createMock(OrganizationApiFacadeInterface::class);
        $facade->expects(self::never())->method('initiateOrganizationDeletion');
        $response = new AdminOrganizationDeleteHandler($facade, new ApiResponseFactory(), new ApiErrorResponseFactory())->handle(new ServerRequest());
        self::assertSame(404, $response->getStatusCode());
    }
}
