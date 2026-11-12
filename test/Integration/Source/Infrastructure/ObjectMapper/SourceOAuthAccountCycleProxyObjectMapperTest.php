<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\ObjectMapper;

use Cycle\ORM\EntityProxyInterface;
use Packio\Source\Api\Dto\SourceOAuthAccountDto;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

final class SourceOAuthAccountCycleProxyObjectMapperTest extends IntegrationTestCase
{
    #[Test]
    public function itMapsAPersistedAndReloadedSourceOAuthAccountCycleProxy(): void
    {
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-object-mapper',
            provider: 'gitlab',
            providerUserId: 'provider-user-object-mapper',
            providerEmail: 'provider@example.test',
            providerUsername: 'provider-user',
            accessToken: 'encrypted-access-token',
            refreshToken: 'encrypted-refresh-token',
        );
        $sourceOAuthAccount->setIdentifier(Uuid::uuid7());
        $this->sourceOAuthAccounts()->save($sourceOAuthAccount);

        $sourceOAuthAccountId = $sourceOAuthAccount->getIdentifier()->toString();

        $this->rebuildContainer();

        $reloadedSourceOAuthAccount = $this->sourceOAuthAccounts()->findById($sourceOAuthAccountId);
        self::assertInstanceOf(SourceOAuthAccount::class, $reloadedSourceOAuthAccount);
        self::assertInstanceOf(EntityProxyInterface::class, $reloadedSourceOAuthAccount);

        $sourceOAuthAccountDto = $this->objectMapper()->map($reloadedSourceOAuthAccount, SourceOAuthAccountDto::class);

        self::assertSame($sourceOAuthAccountId, $sourceOAuthAccountDto->id);
        self::assertSame('gitlab', $sourceOAuthAccountDto->provider);
        self::assertSame('provider-user', $sourceOAuthAccountDto->providerUsername);
        self::assertSame('provider@example.test', $sourceOAuthAccountDto->providerEmail);
    }

    private function sourceOAuthAccounts(): SourceOAuthAccountRepositoryInterface
    {
        $sourceOAuthAccounts = $this->getService(SourceOAuthAccountRepositoryInterface::class);
        self::assertInstanceOf(SourceOAuthAccountRepositoryInterface::class, $sourceOAuthAccounts);

        return $sourceOAuthAccounts;
    }

    private function objectMapper(): ObjectMapperInterface
    {
        $objectMapper = $this->getService(ObjectMapperInterface::class);
        self::assertInstanceOf(ObjectMapperInterface::class, $objectMapper);

        return $objectMapper;
    }
}
