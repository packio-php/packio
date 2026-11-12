<?php

declare(strict_types=1);

namespace Common\Logging;

use Sirix\Redaction\RedactionRuleContextInterface;
use Sirix\Redaction\Rule\RedactionRuleInterface;

use function preg_replace;

/** Masks credentials embedded in diagnostic text, including URLs and headers. */
final readonly class SensitiveTextRule implements RedactionRuleInterface
{
    public function apply(string $value, RedactionRuleContextInterface $context): string
    {
        $value = preg_replace('~\b(Bearer|Basic|token)\s+[a-z0-9._\~+/=-]+~i', '$1 [REDACTED]', $value) ?? '[REDACTED]';
        $value = preg_replace('~([a-z][a-z0-9+.-]*://)[^/\s@]+@~i', '$1[REDACTED]@', $value) ?? '[REDACTED]';
        $value = preg_replace('~((?:access[_-]?token|refresh[_-]?token|token|client[_-]?secret|password|credentials?|secret|api[_-]?key|authorization)["\x27]?\s*[:=]\s*)(?:"[^"]*"|\x27[^\x27]*\x27|[^\s&,"\x27}]+)~i', '$1[REDACTED]', $value) ?? '[REDACTED]';

        return preg_replace('~\b(Bearer|Basic)\s+[a-z0-9._\~+/=-]+~i', '$1 [REDACTED]', $value) ?? '[REDACTED]';
    }
}
