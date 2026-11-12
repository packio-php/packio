<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Common\Logging\SafeExceptionContext;
use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Exception\SourceProviderInstanceError;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Delete;
use Throwable;

use function is_string;

#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Delete(
    '/organization/:organization/source-provider-instances/:id',
    name: 'sources.provider-instances.destroy',
)]
final readonly class SourceProviderInstanceDeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private ?LoggerInterface $logger = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = RequestContextAttributes::currentOrganizationActorContext($request);
        if (! $context instanceof ActorContext) {
            return new JsonResponse([
                'error' => 'Organization not found.',
            ], 404);
        }

        $id = $request->getAttribute('id');
        if (! is_string($id) || '' === $id) {
            return new JsonResponse([
                'error' => 'Source provider instance not found.',
            ], 404);
        }

        try {
            $this->sourceApiFacade->deleteSourceProviderInstanceInContext($context, $id);
        } catch (Throwable $exception) {
            return $this->exceptionResponse($exception, $request);
        }

        return new JsonResponse([
            'deleted' => true,
        ]);
    }

    private function exceptionResponse(Throwable $throwable, ServerRequestInterface $serverRequest): JsonResponse
    {
        $sourceProviderInstanceError = SourceProviderInstanceError::fromException($throwable);
        $this->logger?->error('Source provider instance operation failed.', SafeExceptionContext::forRequest($throwable, $serverRequest));

        return new JsonResponse([
            'error' => $sourceProviderInstanceError->message,
            'code'  => $sourceProviderInstanceError->code,
        ], $sourceProviderInstanceError->status);
    }
}
