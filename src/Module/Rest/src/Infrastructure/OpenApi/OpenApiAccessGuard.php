<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\OpenApi;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;

final readonly class OpenApiAccessGuard
{
    /**
     * @param array{enabled?: bool, public?: bool} $docsConfig
     */
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private ProjectSettingsApiFacadeInterface $projectSettingsApiFacade,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
        private array $docsConfig = [],
    ) {}

    public function deny(ServerRequestInterface $serverRequest): ?ResponseInterface
    {
        if (false === (bool) ($this->docsConfig['enabled'] ?? true)) {
            return $this->apiErrorResponseFactory->disabled();
        }

        if ($this->projectSettingsApiFacade->booleanValue(
            ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC,
            (bool) ($this->docsConfig['public'] ?? true),
        )) {
            return null;
        }

        return $this->currentUser($serverRequest) instanceof UserDto
            ? null
            : $this->apiErrorResponseFactory->unauthorized();
    }

    public function canViewAdminSection(ServerRequestInterface $serverRequest): bool
    {
        $user = $this->currentUser($serverRequest);

        return $user instanceof UserDto && in_array('admin', $user->roles, true);
    }

    private function currentUser(ServerRequestInterface $serverRequest): ?UserDto
    {
        return $this->identityApiFacade->currentUser($serverRequest);
    }
}
