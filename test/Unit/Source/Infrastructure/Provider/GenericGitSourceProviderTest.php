<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Provider;

use InvalidArgumentException;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Infrastructure\GenericGitRepositoryUrlParser;
use Packio\Source\Infrastructure\Provider\GenericGit\GenericGitSourceProvider;
use Packio\Source\Infrastructure\Provider\SourceRegistrationFactory;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PHPUnit\Framework\TestCase;

final class GenericGitSourceProviderTest extends TestCase
{
    public function testUnsafeComposerPathIsRejectedBeforeSourceCreation(): void
    {
        $genericGitSourceProvider = new GenericGitSourceProvider(new GenericGitRepositoryUrlParser(OutboundPolicyFixture::policy()), new SourceRegistrationFactory());
        $this->expectException(InvalidArgumentException::class);
        $genericGitSourceProvider->registerSource('org-1', new SourceRegistrationData('generic_git', 'org-1', '', 'repo', 'git', 'https://git.example.test/team/repo.git', 'main', 'private', composerJsonPath: '../admin'));
    }
}
