<?php

declare(strict_types=1);

namespace Packio\Application\Legal;

use Common\Markdown\MarkdownToHtmlRenderer;
use LogicException;
use RuntimeException;

use function file_get_contents;
use function is_file;
use function is_readable;
use function sprintf;
use function trim;

/** Operator-provided legal pages; a file is read on every render so edits apply without a restart. */
final readonly class LegalDocuments
{
    private string $privacyPolicyPath;
    private string $termsOfServicePath;

    public function __construct(
        string $privacyPolicyPath,
        string $termsOfServicePath,
        private MarkdownToHtmlRenderer $markdownToHtmlRenderer,
    ) {
        $this->privacyPolicyPath  = trim($privacyPolicyPath);
        $this->termsOfServicePath = trim($termsOfServicePath);
    }

    public function isEnabled(LegalDocument $legalDocument): bool
    {
        return '' !== $this->filePath($legalDocument);
    }

    public function renderHtml(LegalDocument $legalDocument): string
    {
        $path = $this->filePath($legalDocument);
        if ('' === $path) {
            throw new LogicException(sprintf('Legal document "%s" is not configured.', $legalDocument->value));
        }

        $markdown = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if (false === $markdown) {
            throw new RuntimeException(sprintf('Legal document file "%s" is missing or unreadable.', $path));
        }

        return $this->markdownToHtmlRenderer->render($markdown);
    }

    /** @return array{privacy_url: null|string, terms_url: null|string} */
    public function sharedProps(): array
    {
        return [
            'privacy_url' => $this->isEnabled(LegalDocument::Privacy) ? LegalDocument::Privacy->path() : null,
            'terms_url'   => $this->isEnabled(LegalDocument::Terms) ? LegalDocument::Terms->path() : null,
        ];
    }

    private function filePath(LegalDocument $legalDocument): string
    {
        return match ($legalDocument) {
            LegalDocument::Privacy => $this->privacyPolicyPath,
            LegalDocument::Terms   => $this->termsOfServicePath,
        };
    }
}
