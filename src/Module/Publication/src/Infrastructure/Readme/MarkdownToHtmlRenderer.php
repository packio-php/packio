<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

use function trim;

final readonly class MarkdownToHtmlRenderer
{
    public function render(string $markdown): string
    {
        $environment = new Environment([
            'allow_unsafe_links' => false,
            'html_input'         => 'strip',
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new DisallowedRawHtmlExtension());
        $markdownConverter = new MarkdownConverter($environment);

        return trim($markdownConverter->convert($markdown)->getContent());
    }
}
