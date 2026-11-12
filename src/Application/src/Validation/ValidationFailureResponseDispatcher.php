<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Application\Security\Http\TrustedProxyRequestOriginResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;
use function preg_match;
use function preg_replace_callback;

final readonly class ValidationFailureResponseDispatcher
{
    public const string DEFAULT_MESSAGE = 'Invalid request data.';

    public const int DEFAULT_JSON_STATUS = 422;

    public const string DEFAULT_LEVEL = 'error';

    public function __construct(
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private ValidationRedirectState $validationRedirectState,
        private ValidationFailureRequestPolicy $validationFailureRequestPolicy,
        private ValidationFailureResponseFactoryInterface $validationFailureResponseFactory,
        private TrustedProxyRequestOriginResolver $trustedProxyRequestOriginResolver,
    ) {}

    /**
     * @param array<string, list<string>> $details
     */
    public function dispatch(
        ServerRequestInterface $serverRequest,
        ValidationTargetPlanItem $validationTargetPlanItem,
        array $details,
    ): ResponseInterface {
        if ($this->validationFailureRequestPolicy->wantsJson($serverRequest)) {
            return $this->validationFailureResponseFactory->validation(
                $serverRequest,
                $details,
                $this->message($validationTargetPlanItem),
                $this->jsonStatus($validationTargetPlanItem),
            );
        }

        $message = $this->message($validationTargetPlanItem);
        $level   = (string) ($validationTargetPlanItem->options['level'] ?? self::DEFAULT_LEVEL);
        $notify  = ValidationNotify::tryFrom((string) ($validationTargetPlanItem->options['notify'] ?? ValidationNotify::Flash->value)) ?? ValidationNotify::Flash;

        if ('' !== $message && ValidationNotify::None !== $notify) {
            if (ValidationNotify::Toast === $notify) {
                $this->inertiaNotificationMessenger->toast($serverRequest, $level, $message);
            } else {
                $this->inertiaNotificationMessenger->flash($serverRequest, $level, $message);
            }
        }

        $this->validationRedirectState->errors($serverRequest, $details);

        if ($validationTargetPlanItem->usesCanonicalDefaultPolicy()) {
            $this->validationRedirectState->old($serverRequest, []);
        } else {
            $this->validationRedirectState->old(
                $serverRequest,
                $this->validationFailureRequestPolicy->allowedOldInput(
                    (array) $serverRequest->getParsedBody(),
                    $validationTargetPlanItem->options['old_input'] ?? [],
                ),
            );
        }

        return new RedirectResponse($this->resolveRedirectPath($serverRequest, $validationTargetPlanItem), 302);
    }

    private function message(ValidationTargetPlanItem $validationTargetPlanItem): string
    {
        return (string) ($validationTargetPlanItem->options['message'] ?? self::DEFAULT_MESSAGE);
    }

    private function jsonStatus(ValidationTargetPlanItem $validationTargetPlanItem): int
    {
        $status = (int) ($validationTargetPlanItem->options['json_status'] ?? self::DEFAULT_JSON_STATUS);

        return $status >= 400 && $status <= 599 ? $status : self::DEFAULT_JSON_STATUS;
    }

    private function resolveRedirectPath(
        ServerRequestInterface $serverRequest,
        ValidationTargetPlanItem $validationTargetPlanItem
    ): string {
        $redirectTo = $validationTargetPlanItem->options['redirect_to'] ?? null;
        if (! is_string($redirectTo) || '' === $redirectTo) {
            $referer = $serverRequest->getHeaderLine('Referer');

            // A path-only Referer ("/x", not "//host" or "/\host") stays on this origin.
            $isLocalPath = 1 === preg_match('#^/(?![/\\\])[^\x00-\x20\x7f]*$#D', $referer);

            return $isLocalPath || $this->trustedProxyRequestOriginResolver->matchesRequestOrigin($serverRequest, $referer, true) ? $referer : '/';
        }

        $resolved = (string) preg_replace_callback('/{([^}]+)}/', static function(array $matches) use ($serverRequest): string {
            $key   = $matches[1];
            $value = $serverRequest->getAttribute($key);

            return is_string($value) ? $value : '';
        }, $redirectTo);

        return '' !== $resolved ? $resolved : '/';
    }
}
