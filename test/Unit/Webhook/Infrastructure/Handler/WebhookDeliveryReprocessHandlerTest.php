<?php

declare(strict_types=1);

namespace PackioTest\Unit\Webhook\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Webhook\Infrastructure\Handler\WebhookDeliveryReprocessHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[CoversClass(WebhookDeliveryReprocessHandler::class)]
final class WebhookDeliveryReprocessHandlerTest extends TestCase
{
    #[Test]
    public function routeUsesWebhooksManagePermissionWithRequestContext(): void
    {
        $reflectionClass = new ReflectionClass(WebhookDeliveryReprocessHandler::class);
        $canAttributes   = $reflectionClass->getAttributes(Can::class);

        self::assertCount(1, $canAttributes);

        /** @var Can $can */
        $can = $canAttributes[0]->newInstance();

        self::assertSame('webhooks.manage', $can->permission);
        self::assertSame([
            'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
            'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
        ], $can->context);
    }

    #[Test]
    public function routeUsesRbacWithoutLegacyManageGuard(): void
    {
        $reflectionClass = new ReflectionClass(WebhookDeliveryReprocessHandler::class);
        $postAttributes  = $reflectionClass->getAttributes(Post::class);

        self::assertCount(1, $postAttributes);

        /** @var Post $post */
        $post = $postAttributes[0]->newInstance();

        self::assertNull($post->middleware);
    }
}
