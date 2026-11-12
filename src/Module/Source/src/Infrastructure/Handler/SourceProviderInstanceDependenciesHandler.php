<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Common\Logging\SafeExceptionContext;
use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Exception\SourceProviderInstanceError;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Response\SourceProviderInstancePresenter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Throwable;

#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get('/organization/:organization/source-provider-instances/:id/dependencies', name: 'sources.provider-instances.dependencies')]
final readonly class SourceProviderInstanceDependenciesHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourceProviderInstancePresenter $sourceProviderInstancePresenter,
        private ?LoggerInterface $logger = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = RequestContextAttributes::currentOrganizationActorContext($request);
        $id      = RouteAttributes::uuid($request, 'id');
        if (! $context instanceof ActorContext || '' === $id) {
            return new JsonResponse([
                'error' => 'Source provider instance not found.',
            ], 404);
        }

        try {
            return new JsonResponse($this->sourceProviderInstancePresenter->dependencies($this->sourceApiFacade->sourceProviderInstanceDependenciesInContext($context, $id)));
        } catch (Throwable $exception) {
            $this->logger?->error('Source provider instance dependencies failed.', SafeExceptionContext::forRequest($exception, $request));
            $error = SourceProviderInstanceError::fromException($exception);

            return new JsonResponse([
                'error' => $error->message,
                'code'  => $error->code,
            ], $error->status);
        }
    }
}
