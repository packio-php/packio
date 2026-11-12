<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Handler;

use InvalidArgumentException;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Publication\Application\Command\ConnectSourcesUseCase;
use Packio\Publication\Infrastructure\Request\ConnectSourcesCommandFactory;
use Packio\Publication\Infrastructure\Request\SourceConnectRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

use function count;
use function sprintf;

#[Post('/organization/:organization/sources/connect', name: 'sources.connect')]
#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[MapRequest(body: SourceConnectRequest::class)]
#[ValidateMappedRequest(SourceConnectRequest::class)]
#[ValidationFailed(
    redirectTo: '/organization/{organization}/sources',
    message: 'Invalid request data.',
    notify: ValidationNotify::Toast,
)]
final readonly class SourceConnectHandler implements RequestHandlerInterface
{
    public function __construct(
        private ConnectSourcesUseCase $connectSourcesUseCase,
        private ConnectSourcesCommandFactory $connectSourcesCommandFactory,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto      = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext         = RequestContextAttributes::requireCurrentOrganizationActorContext($request);

        /** @var SourceConnectRequest $form */
        $form = $request->getAttribute(SourceConnectRequest::class);

        try {
            $command = $this->connectSourcesCommandFactory->create($form, $actorContext);
            $result  = $this->connectSourcesUseCase->execute($command);
            $queued  = count($result->queued);
            $skipped = count($result->skipped);
            $failed  = count($result->failed);
            $type    = 0 === $failed ? 'success' : (0 < $queued + $skipped ? 'warning' : 'error');
            $message = match (true) {
                0 < $failed && 0 === $queued + $skipped => 'Unable to queue sources for import.',
                0 < $failed || 0 < $skipped             => sprintf('%d source(s) queued, %d already queued, %d failed.', $queued, $skipped, $failed),
                default                                 => sprintf('%d source(s) queued for import. You can leave this page.', $queued),
            };
            $this->inertiaNotificationMessenger->toast($request, $type, $message);
        } catch (InvalidArgumentException) {
            $this->inertiaNotificationMessenger->toast($request, 'error', 'Please select at least one valid repository.');
        }

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources');
    }
}
