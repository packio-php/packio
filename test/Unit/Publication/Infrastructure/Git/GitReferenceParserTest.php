<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Git;

use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Git\GitReferenceParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GitReferenceParser::class)]
#[CoversClass(GitReference::class)]
final class GitReferenceParserTest extends TestCase
{
    #[Test]
    public function itParsesBranchesTagsAndPrefersPeeledAnnotatedTagCommit(): void
    {
        $refs = GitReferenceParser::parseLsRemote(<<<'OUT'
            ref: refs/heads/main	HEAD
            aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa	refs/heads/main
            bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb	refs/heads/2.x
            cccccccccccccccccccccccccccccccccccccccc	refs/tags/v1.0.0
            dddddddddddddddddddddddddddddddddddddddd	refs/tags/v1.1.0
            eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee	refs/tags/v1.1.0^{}
            OUT);

        self::assertSame('main', $refs['default_branch']);
        self::assertCount(2, $refs['branches']);
        self::assertSame('2.x', $refs['branches'][1]->name);
        self::assertSame('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $refs['branches'][1]->commit);
        self::assertSame(GitReference::TYPE_BRANCH, $refs['branches'][1]->type);

        self::assertCount(2, $refs['tags']);
        self::assertSame('v1.0.0', $refs['tags'][0]->name);
        self::assertSame('cccccccccccccccccccccccccccccccccccccccc', $refs['tags'][0]->commit);
        self::assertSame(GitReference::TYPE_TAG, $refs['tags'][0]->type);
        self::assertSame('v1.1.0', $refs['tags'][1]->name);
        self::assertSame('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee', $refs['tags'][1]->commit);
    }
}
