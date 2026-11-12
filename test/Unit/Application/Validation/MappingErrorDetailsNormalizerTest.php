<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Validation;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Tree\Message\MessageBuilder;
use CuyZ\Valinor\Mapper\Tree\Message\NodeMessage;
use CuyZ\Valinor\Mapper\TypeTreeMapperError;
use CuyZ\Valinor\MapperBuilder;
use Packio\Application\Validation\MappingErrorDetailsNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(MappingErrorDetailsNormalizer::class)]
final class MappingErrorDetailsNormalizerTest extends TestCase
{
    #[Test]
    public function itNormalizesNestedPathsAndDeduplicatesSafeMessages(): void
    {
        $nodeMessage = new NodeMessage(
            MessageBuilder::newError('Invalid name.')->build(),
            '{original_message}',
            'displayName',
            'profile.displayName',
            'string',
            'string',
            'ignored',
        );
        $typeTreeMapperError = new TypeTreeMapperError('ignored', 'Example', [$nodeMessage, $nodeMessage]);

        self::assertSame([
            'profile.display_name' => ['Invalid value.'],
        ], new MappingErrorDetailsNormalizer()->normalize($typeTreeMapperError));
    }

    #[Test]
    public function itUsesARootKeyAndDoesNotExposeMappingSourceOrTypeDetails(): void
    {
        $secret = 'mapping-normalizer-secret';

        try {
            new MapperBuilder()->mapper()->map('stdClass', $secret);
            self::fail('Mapping should fail.');
        } catch (MappingError $error) {
            $details = new MappingErrorDetailsNormalizer()->normalize($error);

            self::assertSame([
                'root' => ['Invalid value.'],
            ], $details);
            self::assertStringNotContainsString($secret, json_encode($details, JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString('array{}', json_encode($details, JSON_THROW_ON_ERROR));
        }
    }
}
