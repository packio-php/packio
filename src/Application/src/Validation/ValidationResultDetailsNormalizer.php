<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

use Yiisoft\Validator\Result;

use function array_key_exists;
use function array_unique;
use function array_values;

final readonly class ValidationResultDetailsNormalizer
{
    public function __construct(private ValidationErrorKeyNormalizer $validationErrorKeyNormalizer = new ValidationErrorKeyNormalizer()) {}

    /**
     * @return array<string, mixed>
     */
    public function normalize(Result $result): array
    {
        $normalized = [];

        foreach ($result->getErrorMessagesIndexedByProperty() as $property => $messages) {
            $key = $this->validationErrorKeyNormalizer->normalize($property);
            if (! array_key_exists($key, $normalized)) {
                $normalized[$key] = [];
            }

            foreach ((array) $messages as $message) {
                $normalized[$key][] = $message;
            }

            $normalized[$key] = array_values(array_unique($normalized[$key]));
        }

        return $normalized;
    }
}
