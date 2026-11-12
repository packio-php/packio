<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient;

use function array_keys;
use function array_map;
use function count;
use function filter_var;
use function implode;
use function in_array;
use function str_contains;
use function str_starts_with;
use function trim;

final readonly class SafeGitEnvironment
{
    public function __construct(private OutboundUrlPolicy $outboundUrlPolicy = new OutboundUrlPolicy()) {}

    /** @param array<string, string> $environment
     * @return array<string, string>
     */
    public static function sanitize(array $environment): array
    {
        foreach (array_keys($environment) as $name) {
            if (str_starts_with($name, 'GIT_') && ! in_array($name, ['GIT_SSL_CAINFO', 'GIT_SSL_CAPATH'], true)) {
                unset($environment[$name]);
            }
        }

        return $environment;
    }

    /** @return array<string, string> */
    public function forUrl(string $url, string $header = ''): array
    {
        $target    = $this->outboundUrlPolicy->resolve($url);
        $addresses = array_map(static fn (string $ip): string => str_contains($ip, ':') ? '[' . $ip . ']' : $ip, $target['addresses']);
        $settings  = [
            'http.extraHeader'     => $header,
            'http.curloptResolve'  => $target['host'] . ':' . $target['port'] . ':' . implode(',', $addresses),
            'http.followRedirects' => 'false',
            'http.proxy'           => '',
            'http.sslVerify'       => 'true',
            'protocol.allow'       => 'never',
            'protocol.https.allow' => 'always',
            'credential.helper'    => '',
        ];
        if (false !== filter_var(trim($target['host'], '[]'), FILTER_VALIDATE_IP)) {
            unset($settings['http.curloptResolve']);
        }
        $environment = [
            'GIT_CONFIG_NOSYSTEM'   => '1',
            'GIT_CONFIG_SYSTEM'     => '/dev/null',
            'GIT_CONFIG_GLOBAL'     => '/dev/null',
            'GIT_CONFIG_PARAMETERS' => '',
            'GIT_ALLOW_PROTOCOL'    => 'https',
            'GIT_TERMINAL_PROMPT'   => '0',
            'GIT_CONFIG_COUNT'      => (string) count($settings),
            'https_proxy'           => '',
            'HTTPS_PROXY'           => '',
            'http_proxy'            => '',
            'HTTP_PROXY'            => '',
            'all_proxy'             => '',
            'ALL_PROXY'             => '',
            'no_proxy'              => '*',
            'NO_PROXY'              => '*',
        ];
        $index = 0;
        foreach ($settings as $name => $value) {
            $environment['GIT_CONFIG_KEY_' . $index]   = $name;
            $environment['GIT_CONFIG_VALUE_' . $index] = $value;
            ++$index;
        }

        return $environment;
    }
}
