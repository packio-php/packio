<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient;

use function array_unique;
use function array_values;
use function is_string;
use function socket_addrinfo_explain;
use function socket_addrinfo_lookup;

class HostResolver
{
    /** @return list<string> */
    public function resolve(string $host): array
    {
        $records = @socket_addrinfo_lookup($host, null, [
            'ai_socktype' => SOCK_STREAM,
        ]);
        $addresses = [];
        foreach (false === $records ? [] : $records as $record) {
            $info    = socket_addrinfo_explain($record);
            $address = $info['ai_addr']['sin_addr'] ?? $info['ai_addr']['sin6_addr'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }
}
