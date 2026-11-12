<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Request;

use InvalidArgumentException;
use Packio\Publication\Infrastructure\Request\ConnectSourcesCommandFactory;
use Packio\Publication\Infrastructure\Request\SourceConnectRequest;
use Packio\Shared\Domain\Organization\ActorContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConnectSourcesCommandFactoryTest extends TestCase
{
    /** @param list<string> $expected */
    #[DataProvider('inputs')]
    public function testNormalizesInputs(SourceConnectRequest $sourceConnectRequest, array $expected): void
    {
        $connectSourcesCommand = new ConnectSourcesCommandFactory()->create($sourceConnectRequest, new ActorContext('org', 'user', 'owner'));
        self::assertSame($expected, $connectSourcesCommand->repositories);
    }

    /** @return iterable<string, array{SourceConnectRequest, list<string>}> */
    public static function inputs(): iterable
    {
        yield 'single' => [new SourceConnectRequest(resourceUrl: ' owner/repo '), ['owner/repo']];

        yield 'legacy' => [new SourceConnectRequest(repositoryUrl: ' https://host/repo.git '), ['https://host/repo.git']];

        yield 'list and duplicates' => [new SourceConnectRequest(resourceUrl: 'ignored', resourceUrls: [' a/b ', '', 'a/b', 'c/d']), ['a/b', 'c/d']];

        yield 'string list' => [new SourceConnectRequest(resourceUrls: ' a/b '), ['a/b']];
    }

    /** @param list<string> $resources */
    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidInput(array $resources): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ConnectSourcesCommandFactory()->create(new SourceConnectRequest(resourceUrls: $resources), new ActorContext('org', 'user', 'owner'));
    }

    /** @return iterable<array{list<string>}> */
    public static function invalidInputs(): iterable
    {
        yield [[]];

        yield [[' ', '']];

        yield [["owner/\nrepo"]];

        yield [['owner /repo']];
    }

    public function testMapsProviderPayloadAndSettings(): void
    {
        $connectSourcesCommandFactory = new ConnectSourcesCommandFactory();
        $actorContext                 = new ActorContext('org', 'user', 'owner');
        $command                      = $connectSourcesCommandFactory->create(new SourceConnectRequest(provider: 'generic_git', authorizationToken: 'secret', resourceUrl: ' a/b ', composerJsonPath: ' package/composer.json ', keepLastReleases: '3', resourceDefaultRefs: [
            ' a/b ' => ' main ',
        ]), $actorContext);
        self::assertSame([
            'token'       => 'secret',
            'auth_scheme' => 'bearer',
        ], $command->authorizationPayload);
        self::assertSame([
            'a/b' => 'main',
        ], $command->defaultRefs);
        self::assertSame([
            'composer_json_path' => 'package/composer.json',
        ], $command->settings);
        self::assertSame(3, $command->keepLastReleases);
        self::assertSame($actorContext, $command->actorContext);
        $command = $connectSourcesCommandFactory->create(new SourceConnectRequest(provider: 'gitlab_self_hosted', authorizationToken: 'secret', resourceUrl: 'a/b', instanceUrl: ' https://git.example ', providerInstanceId: 'instance'), $actorContext);
        self::assertSame([
            'token'                => 'secret',
            'base_url'             => 'https://git.example',
            'provider_instance_id' => 'instance',
        ], $command->authorizationPayload);
    }
}
