<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Api;

use Packio\Rest\Infrastructure\Request\UpdateSourceRequest;
use Packio\Source\Api\Dto\SourceSettingsPatch;
use Packio\Source\Infrastructure\Request\SourceSettingsRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Yiisoft\Validator\Validator;

final class SourceSettingsInputTest extends TestCase
{
    #[Test]
    public function omittedValuesDifferFromExplicitFalseAndZero(): void
    {
        self::assertEquals(new SourceSettingsPatch(), new SourceSettingsRequest()->toPatch());
        self::assertEquals(new SourceSettingsPatch('private', false, 0), new SourceSettingsRequest([
            'visibility'         => ' private ',
            'active'             => false,
            'keep_last_releases' => 0,
        ])->toPatch());
        $payload = [
            'visibility'       => 'private',
            'active'           => 'on',
            'keepLastReleases' => '12',
        ];
        self::assertEquals(new UpdateSourceRequest($payload)->toPatch(), new SourceSettingsRequest($payload)->toPatch());
        self::assertEquals(new SourceSettingsPatch('private', true, 12), new SourceSettingsRequest($payload)->toPatch());
    }

    #[Test]
    public function attributesAcceptOmittedValuesAndExplicitFalseAndZero(): void
    {
        foreach ([[], [
            'visibility'         => 'private',
            'active'             => false,
            'keep_last_releases' => 0,
        ], [
            'active' => 'on',
        ], [
            'keepLastReleases' => '12',
        ]] as $payload) {
            foreach ([new SourceSettingsRequest($payload), new UpdateSourceRequest($payload)] as $request) {
                self::assertTrue(new Validator()->validate($request)->isValid());
            }
        }
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('invalidPayloads')]
    public function rejectsInvalidValues(array $payload): void
    {
        foreach ([new SourceSettingsRequest($payload), new UpdateSourceRequest($payload)] as $request) {
            $result = new Validator()->validate($request);
            self::assertFalse($result->isValid());
            self::assertCount(1, $result->getErrorMessagesIndexedByProperty());
        }
    }

    /** @return iterable<array{array<string, mixed>}> */
    public static function invalidPayloads(): iterable
    {
        yield [[
            'visibility' => null,
        ]];

        yield [[
            'visibility' => 'secret',
        ]];

        yield [[
            'active' => null,
        ]];

        yield [[
            'active' => [],
        ]];

        yield [[
            'active' => 'invalid',
        ]];

        yield [[
            'keepLastReleases' => null,
        ]];

        yield [[
            'keepLastReleases' => -1,
        ]];

        yield [[
            'keep_last_releases' => '9999999999999999999999999',
        ]];

        yield [[
            'keep_last_releases' => 1.5,
        ]];
    }
}
