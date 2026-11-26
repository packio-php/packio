<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Common\Exception\PublicValidationException;
use InvalidArgumentException;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Infrastructure\Request\ApiAccessTokenIssueRequest;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Throwable;

use function array_filter;
use function array_intersect;
use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function str_contains;
use function trim;

#[Post('/profile/api-tokens', name: 'profile.api-tokens.issue')]
#[Authenticated]
#[MapRequest(body: ApiAccessTokenIssueRequest::class)]
#[ValidateMappedRequest(ApiAccessTokenIssueRequest::class)]
#[ValidationFailed(
    redirectTo: '/profile',
    message: 'Invalid API token request.',
    notify: ValidationNotify::None,
)]
final readonly class ProfileApiTokenIssueHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private ValidationRedirectState $validationRedirectState,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $currentUser = RequestContextAttributes::currentUser($request);
        if (! $currentUser instanceof UserDto) {
            return new RedirectResponse('/auth/login');
        }

        /** @var ApiAccessTokenIssueRequest $form */
        $form = $request->getAttribute(ApiAccessTokenIssueRequest::class);

        $expiresAt = $this->expiresAt($request);

        try {
            $issuedApiAccessTokenDto = $this->identityApiFacade->issueApiAccessTokenForUser(
                $currentUser->id,
                $form->name,
                $this->scopes($request),
                $expiresAt,
            );
        } catch (InvalidArgumentException $exception) {
            $key = $exception instanceof PublicValidationException && str_contains($exception->getMessage(), 'Expiration')
                ? 'expires_at'
                : 'api_token';
            $this->validationRedirectState->errors($request, [
                $key => [$exception->getMessage()],
            ]);

            return new RedirectResponse('/profile');
        } catch (Throwable) {
            $this->validationRedirectState->errors($request, [
                'api_token' => ['Unable to create API token.'],
            ]);

            return new RedirectResponse('/profile');
        }

        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        if ($inertia instanceof InertiaInterface) {
            $inertia->flash('issued_api_token', [
                'name'  => $issuedApiAccessTokenDto->token->name,
                'token' => $issuedApiAccessTokenDto->plainTextToken,
            ]);
        }

        return new RedirectResponse('/profile');
    }

    /**
     * @return list<string>
     */
    private function scopes(ServerRequestInterface $serverRequest): array
    {
        $payload = $serverRequest->getParsedBody();
        $payload = is_array($payload) ? $payload : [];
        $scopes  = $payload['scopes'] ?? [];
        $scopes  = is_array($scopes) ? $scopes : [$scopes];
        $scopes  = array_map(
            static fn (mixed $scope): string => is_string($scope) ? trim($scope) : '',
            $scopes,
        );
        $scopes  = array_values(array_filter($scopes, static fn (string $scope): bool => '' !== $scope));
        // Role-based admin-scope filtering happens in ApiAccessTokenCommandService.
        $scopes = array_values(array_intersect($scopes, ['read', 'write', 'admin']));

        return [] !== $scopes ? $scopes : ['read'];
    }

    private function expiresAt(ServerRequestInterface $serverRequest): ?string
    {
        $payload = $serverRequest->getParsedBody();
        $payload = is_array($payload) ? $payload : [];

        $expiresAt = $payload['expires_at'] ?? null;

        return is_string($expiresAt) && '' !== trim($expiresAt) ? trim($expiresAt) : null;
    }
}
