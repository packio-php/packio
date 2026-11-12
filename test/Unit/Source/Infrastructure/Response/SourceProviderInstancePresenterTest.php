<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Response;

use Packio\Source\Api\Dto\SourceProviderInstanceDependenciesDto;
use Packio\Source\Infrastructure\Response\SourceProviderInstancePresenter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceProviderInstancePresenter::class)]
final class SourceProviderInstancePresenterTest extends TestCase
{
    public function testDependenciesExposeOnlyPublicFieldsAndCountAuthorizationStatuses(): void
    {
        $sourceProviderInstanceDependenciesDto = new SourceProviderInstanceDependenciesDto('instance', 'GitLab', 'https://gitlab.example', 'gitlab_self_hosted', [[
            'id'            => 'source',
            'name'          => 'Repository',
            'url'           => 'https://gitlab.example/repo',
            'provider'      => 'gitlab_self_hosted',
            'status'        => 'active',
            'configuration' => [
                'token' => 'secret',
            ],
        ]], [
            [
                'id'            => 'a',
                'accountName'   => 'Active',
                'provider'      => 'gitlab_self_hosted',
                'authStrategy'  => 'oauth',
                'status'        => 'active',
                'configuration' => [
                    'token' => 'secret',
                ],
            ],
            [
                'id'           => 'b',
                'accountName'  => 'Inactive',
                'provider'     => 'gitlab_self_hosted',
                'authStrategy' => 'token',
                'status'       => 'inactive',
            ],
        ]);

        self::assertSame([
            'instance'                     => [
                'id'            => 'instance',
                'label'         => 'GitLab',
                'base_url'      => 'https://gitlab.example',
                'provider_type' => 'gitlab_self_hosted',
            ],
            'source_count'                 => 1,
            'authorization_count'          => 2,
            'active_authorization_count'   => 1,
            'inactive_authorization_count' => 1,
            'sources'                      => [[
                'id'       => 'source',
                'name'     => 'Repository',
                'url'      => 'https://gitlab.example/repo',
                'provider' => 'gitlab_self_hosted',
                'status'   => 'active',
            ]],
            'authorizations'               => [
                [
                    'id'            => 'a',
                    'account_name'  => 'Active',
                    'provider'      => 'gitlab_self_hosted',
                    'auth_strategy' => 'oauth',
                    'status'        => 'active',
                ],
                [
                    'id'            => 'b',
                    'account_name'  => 'Inactive',
                    'provider'      => 'gitlab_self_hosted',
                    'auth_strategy' => 'token',
                    'status'        => 'inactive',
                ],
            ],
        ], new SourceProviderInstancePresenter()->dependencies($sourceProviderInstanceDependenciesDto));
    }

    public function testEmptyDependenciesKeepListsAndZeroCounts(): void
    {
        self::assertSame([
            'instance'                     => [
                'id'            => 'instance',
                'label'         => 'GitLab',
                'base_url'      => 'https://gitlab.example',
                'provider_type' => 'gitlab_self_hosted',
            ],
            'source_count'                 => 0,
            'authorization_count'          => 0,
            'active_authorization_count'   => 0,
            'inactive_authorization_count' => 0,
            'sources'                      => [],
            'authorizations'               => [],
        ], new SourceProviderInstancePresenter()->dependencies(
            new SourceProviderInstanceDependenciesDto('instance', 'GitLab', 'https://gitlab.example', 'gitlab_self_hosted', [], []),
        ));
    }
}
