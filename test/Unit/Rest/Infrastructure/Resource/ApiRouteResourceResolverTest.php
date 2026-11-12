<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Resource;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Rest\Infrastructure\Resource\ApiRouteResourceResolver;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiRouteResourceResolverTest extends TestCase
{
    #[DataProvider('identifiers')]
    public function testTypedIdentifiersNormalizeOnlyStrings(string $method, string $attribute, mixed $input, string $expected): void
    {
        $serverRequest = new ServerRequest();
        self::assertSame('', ApiRouteResourceResolver::$method($serverRequest));
        self::assertSame($expected, ApiRouteResourceResolver::$method($serverRequest->withAttribute($attribute, $input)));
    }

    /** @return iterable<array{string, string, mixed, string}> */
    public static function identifiers(): iterable
    {
        foreach ([
            'tokenId'                  => 'tokenId',
            'providerAuthorizationId'  => 'authorizationId',
            'sourceProviderInstanceId' => 'instanceId',
            'memberId'                 => 'memberId',
            'invitationId'             => 'invitationId',
            'organizationId'           => 'organizationId',
            'sourceId'                 => 'sourceId',
            'userId'                   => 'userId',
            'jobId'                    => 'jobId',
        ] as $method => $attribute) {
            foreach ([null, 123, [], false, '', ' \t ', '  resource-id  '] as $input) {
                yield [$method, $attribute, $input, ''];
            }

            yield [$method, $attribute, 'urn:uuid:0192F0C6-1C2D-7A3B-8C4D-5E6F7A8B9C0D', '0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d'];

            yield [$method, $attribute, '{0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d}', '0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d'];

            yield [$method, $attribute, '  0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d  ', '0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d'];
        }
    }

    public function testUuidReturnsEmptyStringForNonUuidValues(): void
    {
        $serverRequest = new ServerRequest();
        foreach (['not-a-uuid', '', ' ', null, 123] as $input) {
            self::assertSame('', ApiRouteResourceResolver::uuid($serverRequest->withAttribute('id', $input), 'id'));
        }
        self::assertSame('', ApiRouteResourceResolver::sourceId($serverRequest->withAttribute('sourceId', 'abc')));
        self::assertSame('', ApiRouteResourceResolver::userId($serverRequest->withAttribute('userId', 'abc')));
        self::assertSame('', ApiRouteResourceResolver::jobId($serverRequest->withAttribute('jobId', 'abc')));
    }

    #[DataProvider('scopedResources')]
    public function testLookupDoesNotReturnForeignResources(bool $authorization, string $owner): void
    {
        $id       = '0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d';
        $resource = $authorization
            ? new ProviderAuthorizationDto($id, $owner, 'github', 'token', 'active')
            : SourceDto::fromArray([
                'id'              => $id,
                'organization_id' => $owner,
            ]);
        $facade = $this->createMock(SourceApiFacadeInterface::class);
        if ($authorization) {
            $facade->expects(self::once())->method('findProviderAuthorizationInOrganization')->with($id, 'ours')->willReturn($resource);
        } else {
            $facade->expects(self::once())->method('findSource')->with($id)->willReturn($resource);
        }
        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('ours', 'ours', 'Ours'))
            ->withAttribute($authorization ? 'authorizationId' : 'sourceId', ' ' . $id . ' ')
        ;
        $resolved = $authorization ? ApiRouteResourceResolver::providerAuthorization($serverRequest, $facade) : ApiRouteResourceResolver::source($serverRequest, $facade);
        self::assertSame('ours' === $owner ? $resource : null, $resolved);
    }

    /** @return iterable<array{bool, string}> */
    public static function scopedResources(): iterable
    {
        foreach ([true, false] as $authorization) {
            foreach (['ours', 'foreign'] as $owner) {
                yield [$authorization, $owner];
            }
        }
    }

    public function testMissingIdentifiersDoNotQueryTheFacade(): void
    {
        $facade = $this->createMock(SourceApiFacadeInterface::class);
        $facade->expects(self::never())->method('findSource');
        $facade->expects(self::never())->method('findProviderAuthorizationInOrganization');
        $serverRequest = new ServerRequest()->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('ours', 'ours', 'Ours'));
        self::assertNull(ApiRouteResourceResolver::source($serverRequest, $facade));
        self::assertNull(ApiRouteResourceResolver::providerAuthorization($serverRequest, $facade));
    }
}
