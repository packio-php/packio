<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Handler;

use Common\Exception\PublicValidationException;
use InvalidArgumentException;
use Laminas\Diactoros\ServerRequest;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Rest\Infrastructure\Handler\AdminSettingsUpdateHandler;
use Packio\Rest\Infrastructure\Request\UpdateAdminSettingsRequest;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AdminSettingsErrorHandlerTest extends TestCase
{
    public function testVendorInvalidArgumentIsNotPublicValidation(): void
    {
        $facade = $this->createMock(ProjectSettingsApiFacadeInterface::class);
        $facade->expects(self::once())->method('updateOverrides')->willThrowException(new InvalidArgumentException('token=secret'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $adminSettingsUpdateHandler = new AdminSettingsUpdateHandler($facade, new ApiResponseFactory(), new ApiErrorResponseFactory(), $logger);
        $response                   = $adminSettingsUpdateHandler->handle($this->request());
        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('secret', (string) $response->getBody());
    }

    public function testExplicitPublicValidationMessageIsPreserved(): void
    {
        $facade = $this->createMock(ProjectSettingsApiFacadeInterface::class);
        $facade->expects(self::once())->method('updateOverrides')->willThrowException(new PublicValidationException('Unknown runtime setting key.'));
        $adminSettingsUpdateHandler = new AdminSettingsUpdateHandler($facade, new ApiResponseFactory(), new ApiErrorResponseFactory());
        $response                   = $adminSettingsUpdateHandler->handle($this->request());
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Unknown runtime setting key.', (string) $response->getBody());
    }

    private function request(): ServerRequest
    {
        return new ServerRequest()->withAttribute(UpdateAdminSettingsRequest::class, new UpdateAdminSettingsRequest([
            'settings' => [
                'unknown' => 'on',
            ],
        ]));
    }
}
