<?php

declare(strict_types=1);

namespace PackioTest\Unit\Admin\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Packio\Admin\Infrastructure\Handler\AdminOrganizationDeleteHandler;
use Packio\Admin\Infrastructure\Request\AdminOrganizationDeleteRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Api\Dto\OrganizationDeletionContext;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationOutcome as Outcome;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationResult as Result;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;

final class AdminOrganizationDeleteHandlerTest extends TestCase
{
    #[DataProvider('outcomes')]
    public function testMapsOutcome(Outcome $outcome, string $redirect, string $type, string $message): void
    {
        $facade = $this->createMock(OrganizationApiFacadeInterface::class);
        $facade->expects(self::once())->method('findOrganization')->with('0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d')->willReturn(new OrganizationDto('org', 'org', 'Org'));
        $facade->expects(self::once())->method('initiateOrganizationDeletion')
            ->with(self::callback(static fn (OrganizationDeletionContext $organizationDeletionContext): bool => '0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d' === $organizationDeletionContext->organizationId && 'actor' === $organizationDeletionContext->actorId && 'admin' === $organizationDeletionContext->origin && 'organization.delete.initiated_by_admin' === $organizationDeletionContext->auditEvent))
            ->willReturn(new Result($outcome))
        ;
        $testInertia  = new TestInertia();
        $response     = new AdminOrganizationDeleteHandler($facade, new NullLogger(), new InertiaNotificationMessenger())->handle($this->request($testInertia));
        self::assertSame(302, $response->getStatusCode());
        self::assertSame($redirect, $response->getHeaderLine('Location'));
        self::assertSame([
            $type => $message,
        ], $testInertia->flash);
    }

    /** @return iterable<string, array{Outcome, string, string, string}> */
    public static function outcomes(): iterable
    {
        yield 'initiated' => [Outcome::Initiated, '/admin/organizations', 'success', 'Organization deletion queued.'];

        yield 'repeat' => [Outcome::AlreadyInProgress, '/admin/organizations', 'success', 'Organization deletion queued.'];

        yield 'missing' => [Outcome::NotFound, '/admin/organizations', 'error', 'Organization not found.'];

        yield 'rejected' => [Outcome::Rejected, '/admin/organizations/0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d', 'error', 'Organization is already deleting.'];

        yield 'no recoverable run' => [Outcome::AlreadyDeleting, '/admin/organizations/0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d', 'error', 'Organization is already deleting.'];
    }

    public function testPublicationFailureDoesNotFlashSuccess(): void
    {
        $facade = $this->createMock(OrganizationApiFacadeInterface::class);
        $facade->expects(self::once())->method('findOrganization')->willReturn(new OrganizationDto('org', 'org', 'Org'));
        $facade->expects(self::once())->method('initiateOrganizationDeletion')->willReturn(new Result(Outcome::PublicationFailed));
        $testInertia = new TestInertia();

        try {
            new AdminOrganizationDeleteHandler($facade, new NullLogger(), new InertiaNotificationMessenger())->handle($this->request($testInertia));
            self::fail('Expected publication failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Organization deletion cleanup job could not be published after audit recording.', $exception->getMessage());
            self::assertSame([], $testInertia->flash);
        }
    }

    public function testWrongConfirmationNeverInitiates(): void
    {
        $facade = $this->createMock(OrganizationApiFacadeInterface::class);
        $facade->expects(self::once())->method('findOrganization')->willReturn(new OrganizationDto('org', 'org', 'Org'));
        $facade->expects(self::never())->method('initiateOrganizationDeletion');
        $testInertia  = new TestInertia();
        $response     = new AdminOrganizationDeleteHandler($facade, new NullLogger(), new InertiaNotificationMessenger())->handle($this->request($testInertia)->withAttribute(AdminOrganizationDeleteRequest::class, new AdminOrganizationDeleteRequest('wrong')));
        self::assertSame('/admin/organizations/0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'Type the exact organization name to confirm deletion.',
        ], $testInertia->flash);
    }

    private function request(TestInertia $testInertia): ServerRequest
    {
        return new ServerRequest()->withAttribute('organizationId', '0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d')
            ->withAttribute(RequestContextAttributes::CURRENT_USER_ID, 'actor')
            ->withAttribute(AdminOrganizationDeleteRequest::class, new AdminOrganizationDeleteRequest('Org'))
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
        ;
    }
}
