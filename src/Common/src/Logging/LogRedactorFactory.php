<?php

declare(strict_types=1);

namespace Common\Logging;

use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorInterface;
use Sirix\Redaction\RedactorOptions;
use Sirix\Redaction\Rule\Factory\SharedRuleFactory;

/** A dedicated diagnostic policy; unrelated default PII rules are deliberately disabled. */
final class LogRedactorFactory
{
    public static function create(): RedactorInterface
    {
        return new Redactor(
            customRules: [
                SharedRuleFactory::regexKey(
                    '/^(?:(?:access[_-]?|refresh[_-]?|private[_-]?)?token|client[_-]?secret|password|passwd|secret|credentials?|(?:x[_-]?)?api[_-]?key|(?:proxy[_-]?)?authorization|(?:set[_-]?)?cookie)$/i',
                    SharedRuleFactory::fixedValue('[REDACTED]'),
                ),
                SharedRuleFactory::regexKey(
                    '/^(?:message|error|exception|reason|url|uri|correlation_id|provider|file|class|function)$/i',
                    new SensitiveTextRule(),
                ),
            ],
            useDefaultRules: false,
            redactorOptions: new RedactorOptions(
                maxDepth: 16,
                maxItemsPerContainer: 100,
                maxTotalNodes: 5000,
                overflowPlaceholder: '[REDACTED]',
            ),
        );
    }
}
