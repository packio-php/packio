<?php

declare(strict_types=1);

namespace Packio\Application\Security\Honeypot;

/**
 * Shared contract for honeypot field naming between the field name generator
 * and the validator. Both must agree on the prefix, or the honeypot silently
 * stops protecting forms.
 */
final class HoneypotFieldContract
{
    public const string FIELD_PREFIX = 'h_';

    private function __construct() {}
}
