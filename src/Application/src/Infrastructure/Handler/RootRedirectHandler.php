<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Identity\Api\Dto\OAuthProviderAvailability;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_filter;
use function array_map;
use function array_values;

#[Get('/', name: 'root')]
final readonly class RootRedirectHandler implements RequestHandlerInterface
{
    public function __construct(
        private OAuthApiFacadeInterface $oauthApiFacade,
        private BrandingConfig $brandingConfig,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        $authContext = $request->getAttribute(AuthenticationAttributes::Context->value);
        if ($authContext instanceof AuthContextInterface && $authContext->check()) {
            return new RedirectResponse('/organizations');
        }

        return $inertia->render('Landing', [
            'product_name'    => $this->brandingConfig->productName(),
            'tagline'         => $this->brandingConfig->tagline(),
            'domain'          => $this->brandingConfig->registryBaseDomain(),
            'app_base_url'    => $this->brandingConfig->appBaseUrl(),
            'oauth_providers' => array_map(
                static fn ($provider): array => $provider->toAuthPagePayload(),
                array_values(array_filter(
                    $this->oauthApiFacade->providerAvailabilities(),
                    static fn (OAuthProviderAvailability $oAuthProviderAvailability): bool => $oAuthProviderAvailability->visibleOnAuthPages,
                )),
            ),
        ]);
    }
}
