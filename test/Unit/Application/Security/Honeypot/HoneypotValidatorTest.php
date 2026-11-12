<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Security\Honeypot;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Security\Honeypot\HoneypotValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HoneypotValidator::class)]
final class HoneypotValidatorTest extends TestCase
{
    #[Test]
    public function itIsValidWhenNoRequestBodyProvided(): void
    {
        $honeypotValidator = new HoneypotValidator();

        self::assertTrue($honeypotValidator->validate(new ServerRequest([], [], '/', 'POST')->withParsedBody(null)));
    }

    #[Test]
    public function itIsValidWithEmptyHoneypotField(): void
    {
        $honeypotValidator = new HoneypotValidator();

        self::assertTrue(
            $honeypotValidator->validate(new ServerRequest([], [], '/', 'POST')
                ->withParsedBody([
                    'h_name' => '',
                ])),
        );
    }

    #[Test]
    public function itIsInvalidWithFilledHoneypotField(): void
    {
        $honeypotValidator = new HoneypotValidator();

        self::assertFalse(
            $honeypotValidator->validate(new ServerRequest([], [], '/', 'POST')
                ->withParsedBody([
                    'h_name' => 'bot',
                ])),
        );
    }

    #[Test]
    public function itIgnoresFieldsWithoutHoneypotPrefix(): void
    {
        $honeypotValidator = new HoneypotValidator();

        self::assertTrue(
            $honeypotValidator->validate(new ServerRequest([], [], '/', 'POST')
                ->withParsedBody([
                    'name'    => 'Alice',
                    'comment' => 'hello',
                ])),
        );
    }
}
