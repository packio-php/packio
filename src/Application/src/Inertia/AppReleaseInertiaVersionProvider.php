<?php

declare(strict_types=1);

namespace Packio\Application\Inertia;

use Packio\Application\Release\AppReleaseVersion;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\InertiaPsr15\Service\InertiaVersionProviderInterface;

final readonly class AppReleaseInertiaVersionProvider implements InertiaVersionProviderInterface
{
    public function __construct(private AppReleaseVersion $appReleaseVersion) {}

    public function currentVersion(ServerRequestInterface $request): ?string
    {
        $version = $this->appReleaseVersion->toArray()['sha'];

        return '' === $version ? null : $version;
    }
}
