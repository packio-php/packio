<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response\XmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function rtrim;

#[Get('/sitemap.xml', name: 'sitemap')]
final readonly class SitemapHandler implements RequestHandlerInterface
{
    public function __construct(private BrandingConfig $brandingConfig) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $baseUrl = rtrim($this->brandingConfig->appBaseUrl(), '/');

        $urls = [
            [
                'loc'        => '/',
                'changefreq' => 'weekly',
                'priority'   => '1.0',
            ],
            [
                'loc'        => '/auth/login',
                'changefreq' => 'monthly',
                'priority'   => '0.6',
            ],
            [
                'loc'        => '/auth/register',
                'changefreq' => 'monthly',
                'priority'   => '0.6',
            ],
            [
                'loc'        => '/auth/forgot-password',
                'changefreq' => 'monthly',
                'priority'   => '0.3',
            ],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . $baseUrl . $url['loc'] . "</loc>\n";
            $xml .= '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
            $xml .= '    <priority>' . $url['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>' . "\n";

        return new XmlResponse($xml);
    }
}
