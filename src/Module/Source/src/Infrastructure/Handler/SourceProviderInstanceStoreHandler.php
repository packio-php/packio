<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Common\Logging\SafeExceptionContext;
use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceProviderInstanceRequestDto;
use Packio\Source\Api\Exception\SourceProviderInstanceError;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Request\SourceProviderInstanceRequest;
use Packio\Source\Infrastructure\Response\SourceProviderInstancePresenter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Throwable;

#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post(
    '/organization/:organization/source-provider-instances',
    name: 'sources.provider-instances.store',
)]
#[MapRequest(body: SourceProviderInstanceRequest::class)]
#[ValidateMappedRequest(SourceProviderInstanceRequest::class)]
#[ValidationFailed(message: 'Invalid source provider instance data.')]
final readonly class SourceProviderInstanceStoreHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourceProviderInstancePresenter $sourceProviderInstancePresenter,
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

        /** @var SourceProviderInstanceRequest $form */
        $form = $request->getAttribute(SourceProviderInstanceRequest::class);

        try {
            $instance = $this->sourceApiFacade->createSourceProviderInstanceInContext(
                $context,
                new SourceProviderInstanceRequestDto(
                    providerType: $form->providerType,
                    label: $form->label,
                    baseUrl: $form->baseUrl,
                    clientId: $form->clientId,
                    clientSecret: '' !== $form->clientSecret ? $form->clientSecret : null,
                    authMode: $form->authMode,
                ),
            );
        } catch (Throwable $exception) {
            return $this->exceptionResponse($exception, $request);
        }

        return new JsonResponse([
            'instance' => $this->sourceProviderInstancePresenter->present($instance),
        ], 201);
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
