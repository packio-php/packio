<?php

declare(strict_types=1);

namespace Packio\Application\Inertia;

use Mezzio\Template\TemplateRendererInterface;
use Sirix\InertiaPsr15\Model\Page;
use Sirix\InertiaPsr15\View\RootViewProviderInterface;

final readonly class InertiaSsrRootViewProvider implements RootViewProviderInterface
{
    /**
     * @param non-empty-string $rootView
     */
    public function __construct(
        private TemplateRendererInterface $templateRenderer,
        private InertiaSsrRenderer $inertiaSsrRenderer,
        private string $rootView = 'app.html.twig',
    ) {}

    public function __invoke(Page $page): string
    {
        $ssr = $this->inertiaSsrRenderer->render($page);

        return $this->templateRenderer->render($this->rootView, [
            'page' => $page,
            'ssr'  => $ssr?->toArray(),
        ]);
    }
}
