<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Handler;

use Packio\Application\Legal\LegalDocument;
use Packio\Application\Legal\LegalDocuments;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/privacy', name: 'legal.privacy')]
#[Get('/terms', name: 'legal.terms')]
final readonly class LegalPageHandler implements RequestHandlerInterface
{
    public function __construct(
        private LegalDocuments $legalDocuments,
        private RequestHandlerInterface $notFoundHandler,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $legalDocument = LegalDocument::fromPath($request->getUri()->getPath());
        if (! $legalDocument instanceof LegalDocument || ! $this->legalDocuments->isEnabled($legalDocument)) {
            // Same 404 as an unknown route, without the error-level log an exception would cause.
            return $this->notFoundHandler->handle($request);
        }

        return $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE)->render('Legal/Show', [
            'title' => $legalDocument->title(),
            'html'  => $this->legalDocuments->renderHtml($legalDocument),
        ]);
    }
}
