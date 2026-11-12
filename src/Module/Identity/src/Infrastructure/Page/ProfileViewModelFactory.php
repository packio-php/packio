<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Page;

use Packio\Application\Infrastructure\Resource\ApiAccessTokenResource;
use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Identity\Application\Result\UserOAuthAccountResult;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Shared\Api\Dto\UserDto;

final readonly class ProfileViewModelFactory
{
    /**
     * @param list<UserOAuthAccountResult> $oauthAccountResults
     * @param list<ApiAccessTokenDto>      $tokens
     *
     * @return array<string, mixed>
     */
    public function create(?UserDto $userDto, array $oauthAccountResults, array $tokens): array
    {
        $oauthAccounts   = [];
        $apiAccessTokens = [];

        foreach ($oauthAccountResults as $oauthAccountResult) {
            $provider      = OAuthProvider::tryFrom($oauthAccountResult->provider);
            $providerLabel = $provider?->label() ?? $oauthAccountResult->provider;
            $accountLabel  = $oauthAccountResult->providerUsername
                ?? $oauthAccountResult->providerEmail
                ?? $providerLabel;

            $oauthAccounts[] = [
                'id'                => $oauthAccountResult->id,
                'provider'          => $oauthAccountResult->provider,
                'provider_label'    => $providerLabel,
                'account_label'     => $accountLabel,
                'provider_email'    => $oauthAccountResult->providerEmail,
                'provider_username' => $oauthAccountResult->providerUsername,
            ];
        }

        foreach ($tokens as $token) {
            $apiAccessTokens[] = ApiAccessTokenResource::fromDto($token);
        }

        return [
            'profile'        => $userDto instanceof UserDto ? [
                'id'           => $userDto->id,
                'email'        => $userDto->email,
                'display_name' => $userDto->displayName,
                'roles'        => $userDto->roles,
                'has_password' => $userDto->hasPassword,
                'created_at'   => $userDto->createdAt,
                'updated_at'   => '' !== $userDto->updatedAt ? $userDto->updatedAt : null,
            ] : null,
            'oauth_accounts' => $oauthAccounts,
            'api_tokens'     => $apiAccessTokens,
        ];
    }
}
