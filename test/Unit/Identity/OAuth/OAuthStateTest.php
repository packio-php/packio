<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use Packio\Identity\Infrastructure\Dto\OAuthState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_encode;

final class OAuthStateTest extends TestCase
{
    #[Test]
    #[DataProvider('malformedPayloads')]
    public function decodeRejectsNonStringFields(string $json): void
    {
        self::assertNotInstanceOf(OAuthState::class, OAuthState::decode(base64_encode($json)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedPayloads(): iterable
    {
        yield 'state array' => ['{"s":[1]}'];

        yield 'action int' => ['{"s":"x","a":5}'];

        yield 'organization int' => ['{"s":"x","o":5}'];

        yield 'provider array' => ['{"s":"x","p":["github"]}'];
    }
}
