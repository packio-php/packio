<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Application\Routing\Attribute\CsrfExempt;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

use function array_change_key_case;
use function array_map;
use function implode;
use function strlen;

/**
 * Public endpoint for receiving provider webhooks. The provider must be the one the source was added with.
 * Matches routes like /webhooks/:provider/:sourceId.
 */
#[Post('/webhooks/:provider/:sourceId', name: 'webhooks.handle')]
#[CsrfExempt]
#[RateLimit(
    key: 'webhook',
    by: ['ip', 'route:sourceId'],
    response: 'json',
    message: 'Too many webhook requests. Please try again later.',
)]
final readonly class ReceiveWebhookHandler implements RequestHandlerInterface
{
    public function __construct(private WebhookApiFacadeInterface $webhookApiFacade) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $sourceId = (string) ($request->getAttribute('sourceId') ?? '');
        $provider = (string) ($request->getAttribute('provider') ?? '');
        if ('' === $sourceId || strlen($sourceId) > 64) {
            return new JsonResponse([
                'message' => 'Source ID is required.',
            ], 422);
        }

        foreach ($request->getHeaders() as $name => $values) {
            if (strlen($name) > 128 || strlen(implode(',', $values)) > 8192) {
                return new JsonResponse([
                    'message' => 'Webhook headers are too long.',
                ], 400);
            }
        }
        $headers = array_map(
            static fn (array $values): string => $values[0] ?? '',
            array_change_key_case($request->getHeaders(), CASE_LOWER),
        );
        $payload = (string) $request->getBody();

        $sourceUuid = RouteAttributes::uuid($request, 'sourceId');
        if ('' === $sourceUuid) {
            return new JsonResponse([
                'message' => 'Source not found.',
            ], 404);
        }

        $webhookResultDto = $this->webhookApiFacade->handleWebhook($sourceUuid, $headers, $payload, $provider);

        return new JsonResponse(
            [
                'message' => $webhookResultDto->message,
            ],
            $webhookResultDto->statusCode,
        );
    }
}
