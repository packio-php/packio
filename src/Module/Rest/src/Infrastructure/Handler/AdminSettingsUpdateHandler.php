<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Common\Exception\PublicValidationException;
use Common\Logging\SafeExceptionContext;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Rest\Attribute\ApiAudited;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Request\UpdateAdminSettingsRequest;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Patch;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Throwable;

use function is_scalar;

#[Patch('/api/v1/admin/settings', name: 'api.v1.admin.settings.update')]
#[ApiAuthenticated]
#[ApiScope('admin')]
#[RateLimit(
    key: 'api_admin_write',
    by: ['ip'],
    response: 'json',
    message: 'Too many admin API write requests. Please try again later.'
)]
#[MapRequest(source: UpdateAdminSettingsRequest::class)]
#[ValidateMappedRequest(UpdateAdminSettingsRequest::class)]
#[ApiAudited]
#[Can('admin.access')]
final readonly class AdminSettingsUpdateHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProjectSettingsApiFacadeInterface $projectSettingsApiFacade,
        private ApiResponseFactory $apiResponseFactory,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
        private ?LoggerInterface $logger = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var UpdateAdminSettingsRequest $form */
        $form     = $request->getAttribute(UpdateAdminSettingsRequest::class);

        /** @var array<string, mixed> $settings Validated by ValidateMappedRequest. */
        $settings = $form->settings();

        $values = [];
        foreach ($settings as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $values[(string) $key] = (string) $value;
        }

        try {
            $this->projectSettingsApiFacade->updateOverrides(
                $values,
                RequestContextAttributes::currentUserId($request),
            );
        } catch (PublicValidationException $exception) {
            // This type contains only explicit, fixed public validation messages.
            return $this->apiErrorResponseFactory->validation([
                'settings' => [$exception->getMessage()],
            ]);
        } catch (Throwable $exception) {
            $this->logger?->error('API operation failed.', SafeExceptionContext::forRequest($exception, $request));

            return $this->apiErrorResponseFactory->error('internal_error', 'Unable to complete the operation.', 500);
        }

        return $this->apiResponseFactory->json([
            'data' => $this->projectSettingsApiFacade->settingsPayload(),
        ]);
    }
}
