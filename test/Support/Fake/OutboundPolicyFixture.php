<?php

declare(strict_types=1);

namespace PackioTest\Support\Fake;

use Common\Infrastructure\HttpClient\HostResolver;
use Common\Infrastructure\HttpClient\OutboundUrlPolicy;
use Common\Infrastructure\HttpClient\SafeGitEnvironment;

final class OutboundPolicyFixture
{
    public static function policy(): OutboundUrlPolicy
    {
        return new OutboundUrlPolicy(hostResolver: new class extends HostResolver {
            public function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
    }

    public static function git(): SafeGitEnvironment
    {
        return new SafeGitEnvironment(self::policy());
    }
}
