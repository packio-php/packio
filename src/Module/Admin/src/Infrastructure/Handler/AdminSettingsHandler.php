<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/admin/settings', name: 'admin.settings')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminSettingsHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProjectSettingsApiFacadeInterface $projectSettingsApiFacade,
        private BrandingConfig $brandingConfig,
        /** @var array<string, mixed> $appConfig */
        private array $appConfig,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        return $inertia->render('Admin/Settings', [
            'settings' => $this->projectSettingsApiFacade->settingsPayload(),
            'system'   => [
                'branding'                 => $this->brandingConfig->toArray(),
                'app_debug'                => (bool) ($this->appConfig['debug'] ?? false),
                'app_timezone'             => (string) ($this->appConfig['timezone'] ?? 'UTC'),
                'app_production_mode'      => (bool) ($this->appConfig['production_mode'] ?? false),
                'registry_base_path'       => (string) ($this->appConfig['registry_base_path'] ?? '/repo'),
                'registry_metadata_format' => (string) ($this->appConfig['registry_metadata_format'] ?? 'p2'),
                'registry_cache_ttl'       => (int) ($this->appConfig['registry_cache_ttl'] ?? 300),
            ],
        ]);
    }
}
