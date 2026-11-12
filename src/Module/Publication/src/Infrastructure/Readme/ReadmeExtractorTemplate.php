<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

final readonly class ReadmeExtractorTemplate
{
    public function __construct(
        private ReadmeCandidateProvider $readmeCandidateProvider,
        private MarkdownToHtmlRenderer $markdownToHtmlRenderer,
    ) {}

    /**
     * @param callable(string): ?string   $fetchCandidate
     * @param null|callable(string): void $onMissingCandidate
     * @param null|callable(string): void $onFoundCandidate
     */
    public function extract(
        callable $fetchCandidate,
        ?callable $onMissingCandidate = null,
        ?callable $onFoundCandidate = null,
    ): ?string {
        foreach ($this->readmeCandidateProvider->candidates() as $path) {
            $markdown = $fetchCandidate($path);
            if (null === $markdown) {
                if (null !== $onMissingCandidate) {
                    $onMissingCandidate($path);
                }

                continue;
            }

            if (null !== $onFoundCandidate) {
                $onFoundCandidate($path);
            }

            return $this->render($markdown);
        }

        return null;
    }

    public function render(string $markdown): string
    {
        return $this->markdownToHtmlRenderer->render($markdown);
    }
}
