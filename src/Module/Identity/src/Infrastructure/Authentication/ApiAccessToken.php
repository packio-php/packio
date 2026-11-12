<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Authentication;

use Packio\Identity\Api\Authentication\ApiAccessTokenContract;
use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

use function strtotime;

final readonly class ApiAccessToken implements TokenInterface
{
    public function __construct(private ApiAccessTokenDto $apiAccessTokenDto) {}

    public function getId(): string
    {
        return $this->apiAccessTokenDto->id;
    }

    public function getStorage(): string
    {
        return ApiAccessTokenContract::STORAGE;
    }

    public function getPayload(): array
    {
        return [
            ApiAccessTokenContract::PAYLOAD_ACCESS_TOKEN => $this->apiAccessTokenDto,
            'api_token_id'                               => $this->apiAccessTokenDto->id,
            'user_id'                                    => $this->apiAccessTokenDto->userId,
            'scopes'                                     => $this->apiAccessTokenDto->scopes,
        ];
    }

    public function getExpiresAt(): ?int
    {
        if ('' === $this->apiAccessTokenDto->expiresAt) {
            return null;
        }

        $timestamp = strtotime($this->apiAccessTokenDto->expiresAt);

        return false === $timestamp ? null : $timestamp;
    }
}
