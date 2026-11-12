<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Cake\Chronos\Chronos;
use Packio\Source\Domain\Dto\SourceOAuthRefreshCriteria;
use Packio\Source\Domain\Entity\SourceOAuthAccount;

use function is_string;
use function max;
use function trim;

final readonly class SourceOAuthRefreshPolicy
{
    private const string DEFAULT_REFRESH_BEFORE           = '24 hours';
    private const string DEFAULT_IDLE_BEFORE              = '30 days';
    private const string DEFAULT_MINIMUM_REFRESH_INTERVAL = '1 day';
    private const int DEFAULT_LIMIT                       = 100;

    public function __construct(private SourceOAuthRefreshOptions $sourceOAuthRefreshOptions = new SourceOAuthRefreshOptions()) {}

    public function criteria(
        ?string $provider = null,
        ?string $providerInstanceId = null,
        ?string $organizationId = null,
        ?int $limit = null,
        ?string $refreshBefore = null,
        ?string $idleBefore = null,
        ?string $minimumRefreshInterval = null,
        ?Chronos $now = null,
    ): SourceOAuthRefreshCriteria {
        $resolvedNow      = $now ?? new Chronos();
        $resolvedProvider = $this->nonEmpty($provider);

        return new SourceOAuthRefreshCriteria(
            tokenExpiresBefore: $this->futureCutoff(
                $resolvedNow,
                $refreshBefore ?? $this->configValue($resolvedProvider, 'refresh_before', self::DEFAULT_REFRESH_BEFORE),
            ),
            lastUsedBefore: $this->pastCutoff(
                $resolvedNow,
                $idleBefore ?? $this->configValue($resolvedProvider, 'idle_before', self::DEFAULT_IDLE_BEFORE),
            ),
            updatedBefore: $this->pastCutoff(
                $resolvedNow,
                $minimumRefreshInterval ?? $this->configValue($resolvedProvider, 'minimum_refresh_interval', self::DEFAULT_MINIMUM_REFRESH_INTERVAL),
            ),
            provider: $resolvedProvider,
            providerInstanceId: $this->nonEmpty($providerInstanceId),
            organizationId: $this->nonEmpty($organizationId),
            limit: max(1, $limit ?? $this->defaultLimit()),
        );
    }

    public function reasonFor(
        SourceOAuthAccount $sourceOAuthAccount,
        SourceOAuthRefreshCriteria $sourceOAuthRefreshCriteria
    ): SourceOAuthRefreshReason {
        $expiresAt = $sourceOAuthAccount->tokenExpiresAt();
        if ($expiresAt instanceof Chronos && $expiresAt <= $sourceOAuthRefreshCriteria->tokenExpiresBefore) {
            return SourceOAuthRefreshReason::ExpiresSoon;
        }

        return SourceOAuthRefreshReason::IdleKeepalive;
    }

    private function futureCutoff(Chronos $now, string $duration): Chronos
    {
        return new Chronos($now->toDateTimeString() . ' +' . $this->normalizeDuration($duration));
    }

    private function pastCutoff(Chronos $now, string $duration): Chronos
    {
        return new Chronos($now->toDateTimeString() . ' -' . $this->normalizeDuration($duration));
    }

    private function normalizeDuration(string $duration): string
    {
        $duration = trim($duration);

        return '' !== $duration ? $duration : self::DEFAULT_MINIMUM_REFRESH_INTERVAL;
    }

    private function defaultLimit(): int
    {
        return $this->sourceOAuthRefreshOptions->limit > 0 ? $this->sourceOAuthRefreshOptions->limit : self::DEFAULT_LIMIT;
    }

    private function configValue(?string $provider, string $key, string $default): string
    {
        if (null !== $provider) {
            $providerValue = match ($key) {
                'refresh_before'           => $this->sourceOAuthRefreshOptions->provider($provider)?->refreshBefore,
                'idle_before'              => $this->sourceOAuthRefreshOptions->provider($provider)?->idleBefore,
                'minimum_refresh_interval' => $this->sourceOAuthRefreshOptions->provider($provider)?->minimumRefreshInterval,
                default                    => null,
            };

            if (is_string($providerValue) && '' !== trim($providerValue)) {
                return $providerValue;
            }
        }

        $value = match ($key) {
            'refresh_before'           => $this->sourceOAuthRefreshOptions->refreshBefore,
            'idle_before'              => $this->sourceOAuthRefreshOptions->idleBefore,
            'minimum_refresh_interval' => $this->sourceOAuthRefreshOptions->minimumRefreshInterval,
            default                    => null,
        };

        return is_string($value) && '' !== trim($value) ? $value : $default;
    }

    private function nonEmpty(?string $value): ?string
    {
        $value = trim((string) $value);

        return '' !== $value ? $value : null;
    }
}
