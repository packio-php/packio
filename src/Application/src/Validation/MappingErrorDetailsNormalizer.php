<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

use CuyZ\Valinor\Mapper\MappingError;
use Sirix\Mezzio\Valinor\Error\RequestInputError;

use function array_key_exists;
use function array_map;
use function array_values;
use function in_array;

final readonly class MappingErrorDetailsNormalizer
{
    /**
     * Valinor messages embed user input, so they are never surfaced verbatim. The
     * client signal is carried by the normalized field key; the message text is
     * replaced with this stable value.
     */
    private const string INVALID_VALUE_MESSAGE = 'Invalid value.';

    /**
     * RequestInputError messages are fixed package texts without embedded user
     * input, so they are safe to surface verbatim.
     */
    private const string REQUEST_INPUT_ERROR_KEY = 'root';

    public function __construct(private ValidationErrorKeyNormalizer $validationErrorKeyNormalizer = new ValidationErrorKeyNormalizer()) {}

    /**
     * @return array<string, list<string>>
     */
    public function normalize(MappingError|RequestInputError $mappingError): array
    {
        if ($mappingError instanceof RequestInputError) {
            return [
                self::REQUEST_INPUT_ERROR_KEY => [$mappingError->getMessage()],
            ];
        }

        /** @var array<string, list<string>> $details */
        $details = [];

        foreach ($mappingError->messages()->formatWith() as $message) {
            $key  = '*root*' === $message->path() ? 'root' : $this->validationErrorKeyNormalizer->normalize($message->path());
            $text = self::INVALID_VALUE_MESSAGE;

            if (! array_key_exists($key, $details)) {
                $details[$key] = [];
            }

            if (! in_array($text, $details[$key], true)) {
                $details[$key][] = $text;
            }
        }

        return array_map(array_values(...), $details);
    }
}
