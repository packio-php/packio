<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Registry\Api\RegistryApiFacadeInterface;
use Packio\Registry\Infrastructure\Handler\RegistryPackageMetadataHandler;
use Packio\Registry\Infrastructure\Handler\RegistryPackagesHandler;
use Packio\Registry\Infrastructure\Handler\RegistryPrivatePackagesHandler;
use Packio\Registry\Infrastructure\RegistryMetadataUrlNormalizer;
use Packio\Shared\Api\Dto\OrganizationDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function json_decode;

#[CoversClass(RegistryMetadataUrlNormalizer::class)]
#[CoversClass(RegistryPackagesHandler::class)]
#[CoversClass(RegistryPrivatePackagesHandler::class)]
#[CoversClass(RegistryPackageMetadataHandler::class)]
final class RegistryMetadataUrlNormalizerTest extends TestCase
{
    public function testNormalizesOnlyRootRelativeMetadataUrlsAndPreservesPayload(): void
    {
        $registryMetadataUrlNormalizer = new RegistryMetadataUrlNormalizer(new BrandingConfig([
            'app_url'              => 'https://app.example:8080',
            'registry_base_domain' => 'registry.example:8443',
        ]));
        $payload = [
            'metadata-url' => '/p2/%package%?version=1#metadata',
            'packages'     => [
                'vendor/package' => [
                    [
                        'dist' => [
                            'url' => '/dist/a%20b.zip?token=a%2Fb#download',
                        ],
                    ],
                    [
                        'source' => [
                            'url' => 'https://github.com/vendor/package.git',
                        ],
                    ],
                    [
                        'url' => '//cdn.example/archive.zip',
                    ],
                    [
                        'url' => 'relative/archive.zip',
                    ],
                    [
                        'url' => '?query=1',
                    ],
                    [
                        'url' => '#fragment',
                    ],
                    [
                        'url' => '',
                    ],
                    [
                        'url' => null,
                    ],
                ],
            ],
            'other'        => '/unchanged',
            'list'         => [false, 42, '/unchanged'],
        ];
        $expected                                                 = $payload;
        $expected['metadata-url']                                 = 'https://acme.registry.example:8443/p2/%package%?version=1#metadata';
        $expected['packages']['vendor/package'][0]['dist']['url'] = 'https://acme.registry.example:8443/dist/a%20b.zip?token=a%2Fb#download';

        self::assertSame($expected, $registryMetadataUrlNormalizer->normalize($payload, 'acme'));
        self::assertSame($expected, $registryMetadataUrlNormalizer->normalize($expected, 'acme'));
        self::assertSame('/p2/%package%?version=1#metadata', $payload['metadata-url']);
    }

    public function testAllEndpointsUseCanonicalOrganizationOriginRegardlessOfHostAndProxyHeaders(): void
    {
        $registryMetadataUrlNormalizer = new RegistryMetadataUrlNormalizer(new BrandingConfig([
            'app_url'              => 'https://app.example',
            'registry_base_domain' => 'registry.example',
        ]));
        $payload = [
            'metadata-url' => '/p2/%package%',
            'packages'     => [[
                'dist' => [
                    'url' => '/dist/file.zip',
                ],
            ]],
        ];
        $facade = $this->createMock(RegistryApiFacadeInterface::class);
        $facade->expects(self::once())->method('publicRegistryPayload')->with('org-01')->willReturn($payload);
        $facade->expects(self::once())->method('privateRegistryPayload')->with('org-01')->willReturn($payload);
        $facade->expects(self::once())->method('packageMetadata')->with('vendor/package', 'org-01')->willReturn($payload);
        $serverRequest = new ServerRequest(uri: 'http://internal:8080')
            ->withHeader('Host', 'untrusted.example')
            ->withHeader('Forwarded', 'host=proxy.example;proto=http')
            ->withHeader('X-Forwarded-Host', 'proxy.example')
            ->withHeader('X-Forwarded-Proto', 'http')
            ->withHeader('X-Forwarded-Port', '9999')
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-01', 'acme', 'Acme', anonymousAccess: true))
            ->withAttribute('vendor', 'vendor')
            ->withAttribute('package', 'package')
        ;
        $expected = [
            'metadata-url' => 'https://acme.registry.example/p2/%package%',
            'packages'     => [[
                'dist' => [
                    'url' => 'https://acme.registry.example/dist/file.zip',
                ],
            ]],
        ];

        foreach ([
            new RegistryPackagesHandler($facade, $registryMetadataUrlNormalizer),
            new RegistryPrivatePackagesHandler($facade, $registryMetadataUrlNormalizer),
            new RegistryPackageMetadataHandler($facade, $registryMetadataUrlNormalizer),
        ] as $handler) {
            $response = $handler->handle($serverRequest);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame($expected, json_decode((string) $response->getBody(), true));
        }
    }
}
