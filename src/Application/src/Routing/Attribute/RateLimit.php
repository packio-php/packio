<?php

declare(strict_types=1);

namespace Packio\Application\Routing\Attribute;

use Attribute;
use InvalidArgumentException;
use Packio\Application\Middleware\RateLimitMiddleware;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function sort;
use function strtoupper;
use function trim;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class RateLimit implements RouteAttributeModifierInterface
{
    private const array ALLOWED_RESPONSE_TYPES = ['json', 'redirect', 'empty'];
    private const array ALLOWED_BY_STRATEGIES  = ['ip', 'user', 'route', 'body'];

    /**
     * @var list<string>
     */
    public array $methods;

    /** @var list<string> normalized 'by' strategies */
    public array $by;

    /**
     * @param string                    $key        Policy key in config.security.rate_limits
     * @param array<int, string>|string $by         Strategy for building subject key ('ip', 'user', 'body:<field>', 'route:<attribute>')
     * @param string                    $response   Response mode: 'json', 'redirect', 'empty'
     * @param null|string               $message    Error message for response
     * @param int                       $status     HTTP status code for throttled response
     * @param null|string               $redirectTo Redirect target when response='redirect'
     * @param string                    $level      Flash message level
     * @param string                    $notify     Notification mode: 'flash', 'toast', 'none'
     * @param array<int, string>        $methods    HTTP methods this rule applies to (empty = all)
     */
    public function __construct(
        public string $key,
        array|string $by = ['ip'],
        public string $response = 'json',
        public ?string $message = null,
        public int $status = 429,
        public ?string $redirectTo = null,
        public string $level = 'error',
        public string $notify = 'flash',
        array $methods = [],
    ) {
        if ('' === trim($this->key)) {
            throw new InvalidArgumentException('RateLimit: $key must be a non-empty string.');
        }

        if (! in_array($this->response, self::ALLOWED_RESPONSE_TYPES, true)) {
            throw new InvalidArgumentException(
                'RateLimit: $response must be one of: ' . implode(', ', self::ALLOWED_RESPONSE_TYPES) . '.',
            );
        }

        if ($this->status < 400 || $this->status > 599) {
            throw new InvalidArgumentException('RateLimit: $status must be between 400 and 599.');
        }

        // Normalize 'by' strategies
        $byArray      = is_string($by) ? [$by] : $by;
        $normalizedBy = [];
        foreach ($byArray as $strategy) {
            $strategy = trim((string) $strategy);
            if ('' === $strategy) {
                continue;
            }

            $parts = explode(':', $strategy, 2);
            $base  = $parts[0];

            if (! in_array($base, self::ALLOWED_BY_STRATEGIES, true)) {
                throw new InvalidArgumentException(
                    'RateLimit: invalid "by" strategy "' . $base . '". Allowed: ' . implode(', ', self::ALLOWED_BY_STRATEGIES) . '.',
                );
            }

            $normalizedBy[] = $strategy;
        }

        $normalizedBy = array_values(array_unique($normalizedBy));
        $this->by     = [] !== $normalizedBy ? $normalizedBy : ['ip'];

        // Normalize methods
        $methods = array_filter($methods, static fn (string $method): bool => '' !== $method)
                |> (fn ($x) => array_map(static fn (string $method): string => strtoupper(trim($method)), $x))
                |> array_unique(...)
                |> array_values(...);
        sort($methods);

        $this->methods = $methods;
    }

    public function getMiddleware(): array
    {
        return [RateLimitMiddleware::class];
    }

    public function getDefaults(): array
    {
        return [
            'rate_limit' => [
                [
                    'key'           => $this->key,
                    'by'            => $this->by,
                    'response'      => $this->response,
                    'message'       => $this->message,
                    'status'        => $this->status,
                    'redirect_to'   => $this->redirectTo,
                    'level'         => $this->level,
                    'notify'        => $this->notify,
                    'methods'       => $this->methods,
                ],
            ],
        ];
    }
}
