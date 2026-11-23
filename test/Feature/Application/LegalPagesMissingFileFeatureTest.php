<?php

declare(strict_types=1);

namespace PackioTest\Feature\Application;

use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function getenv;
use function putenv;

/** Separate class: the env must be set before the application boots in parent::setUp(). */
final class LegalPagesMissingFileFeatureTest extends FeatureTestCase
{
    private false|string $previousTermsPath;

    protected function setUp(): void
    {
        $this->previousTermsPath = getenv('TERMS_OF_SERVICE_PATH');
        putenv('TERMS_OF_SERVICE_PATH=/nonexistent/terms.md');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv(false === $this->previousTermsPath ? 'TERMS_OF_SERVICE_PATH' : 'TERMS_OF_SERVICE_PATH=' . $this->previousTermsPath);

        parent::tearDown();
    }

    #[Test]
    public function missingFileIs500AndOtherPagesStillWork(): void
    {
        $this->get('/terms')->assertStatus(500);
        $this->get('/auth/login')->assertStatus(200);
    }
}
