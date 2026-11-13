<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Post('/admin/users/:userUuid/verify', name: 'admin.users.verify')]
#[Authenticated]
#[Can('admin.access')]
final readonly class AdminUserVerifyHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private AuditApiFacadeInterface $auditApiFacade,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userUuid = RouteAttributes::uuid($request, 'userUuid');

        if ('' === $userUuid || ! $this->identityApiFacade->markUserEmailAsVerified($userUuid)) {
            return new JsonResponse([
                'error' => 'User not found',
            ], 404);
        }

        $this->auditApiFacade->record(
            event: 'identity.user.email_verified_by_admin',
            context: [],
            actorId: RequestContextAttributes::currentUserId($request) ?? '',
            subjectType: 'user',
            subjectId: $userUuid,
        );

        return new JsonResponse([
            'success' => true,
        ]);
    }
}
