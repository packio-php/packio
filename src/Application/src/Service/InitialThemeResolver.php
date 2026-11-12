<?php

declare(strict_types=1);

namespace Packio\Application\Service;

use Psr\Http\Message\ServerRequestInterface;

use function in_array;

final readonly class InitialThemeResolver
{
    private const string LIGHT_PAGE_BG = '#f3f4f7';
    private const string DARK_PAGE_BG  = '#1a1f2a';

    /**
     * @return array{mode: string, resolved: null|string, pageBg: string, lightPageBg: string, darkPageBg: string}
     */
    public function resolve(ServerRequestInterface $serverRequest, string $storageKey): array
    {
        $mode     = $this->themeModeFromCookie($serverRequest, $storageKey);
        $resolved = in_array($mode, ['dark', 'light'], true) ? $mode : null;

        return [
            'mode'        => $mode,
            'resolved'    => $resolved,
            'pageBg'      => 'dark' === $resolved ? self::DARK_PAGE_BG : self::LIGHT_PAGE_BG,
            'lightPageBg' => self::LIGHT_PAGE_BG,
            'darkPageBg'  => self::DARK_PAGE_BG,
        ];
    }

    private function themeModeFromCookie(ServerRequestInterface $serverRequest, string $storageKey): string
    {
        $cookies = $serverRequest->getCookieParams();
        $theme   = $cookies[$storageKey] ?? null;

        return in_array($theme, ['dark', 'light', 'system'], true) ? $theme : 'system';
    }
}
