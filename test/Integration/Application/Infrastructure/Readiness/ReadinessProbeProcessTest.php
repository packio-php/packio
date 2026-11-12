<?php

declare(strict_types=1);

namespace PackioTest\Integration\Application\Infrastructure\Readiness;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function fclose;
use function file_put_contents;
use function fwrite;
use function hrtime;
use function is_resource;
use function json_encode;
use function mkdir;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function random_bytes;
use function rmdir;
use function stream_get_contents;
use function stream_set_blocking;
use function sys_get_temp_dir;
use function unlink;
use function usleep;

final class ReadinessProbeProcessTest extends TestCase
{
    #[Test]
    public function malformedJsonFailsSilently(): void
    {
        $this->assertProcessFailsSilently('{"must-not-leak":');
    }

    #[Test]
    public function nonPayloadJsonFailsSilently(): void
    {
        foreach (['null', '"not a payload"', '1', 'true', '{}', '[]'] as $json) {
            $this->assertProcessFailsSilently($json);
        }
    }

    #[Test]
    public function payloadWithoutNullableCredentialsFailsSilently(): void
    {
        $payload = [
            'dsn'              => 'pgsql:invalid-dsn',
            'schemas'          => ['public'],
            'schema_file'      => '/missing/readiness-schema.php',
            'migrations_dir'   => '/missing/migrations',
            'migrations_table' => 'migration',
            'archive'          => '/missing/archive',
            'cache'            => [],
            'settings'         => [],
        ];

        $this->assertProcessFailsSilently(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function negativeProbeResultFailsSilently(): void
    {
        $this->assertProcessFailsSilently(json_encode([
            'dsn'              => 'pgsql:host=127.0.0.1;port=1;dbname=missing',
            'user'             => null,
            'password'         => null,
            'schemas'          => ['public'],
            'schema_file'      => '/missing/readiness-schema.php',
            'migrations_dir'   => '/missing/migrations',
            'migrations_table' => 'migration',
            'archive'          => '/missing/archive',
            'cache'            => [],
            'settings'         => [],
        ], JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function probeThrowableFailsSilently(): void
    {
        $directory = sys_get_temp_dir() . '/packio-readiness-process-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o700);
        $schema = $directory . '/schema.php';
        file_put_contents($schema, '<?php return ["table" => ["table" => "readiness", "columns" => ["id" => "id"]]];');

        try {
            $this->assertProcessFailsSilently(json_encode([
                'dsn'              => 'pgsql:invalid-dsn',
                'user'             => null,
                'password'         => null,
                'schemas'          => ['public'],
                'schema_file'      => $schema,
                'migrations_dir'   => $directory,
                'migrations_table' => 'migration',
                'archive'          => $directory,
                'cache'            => [$directory],
                'settings'         => [],
            ], JSON_THROW_ON_ERROR));
        } finally {
            unlink($schema);
            rmdir($directory);
        }
    }

    private function assertProcessFailsSilently(string $input): void
    {
        $root    = dirname(__DIR__, 5);
        $process = proc_open(
            [PHP_BINARY, $root . '/bin/readiness-probe.php'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $root,
        );
        self::assertIsResource($process);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        try {
            fwrite($pipes[0], $input);
            fclose($pipes[0]);
            $stdout   = '';
            $stderr   = '';
            $deadline = hrtime(true) + 2_000_000_000;

            do {
                $stdout .= stream_get_contents($pipes[1]);
                $stderr .= stream_get_contents($pipes[2]);
                $status = proc_get_status($process);
                if (! $status['running']) {
                    break;
                }
                usleep(1_000);
            } while (hrtime(true) < $deadline);

            if ($status['running']) {
                proc_terminate($process, 9);
                self::fail('The readiness probe process did not finish within the test deadline.');
            }

            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            self::assertSame(1, $status['exitcode']);
            self::assertSame('', $stdout);
            self::assertSame('', $stderr);
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            if (proc_get_status($process)['running']) {
                proc_terminate($process, 9);
            }
            proc_close($process);
        }
    }
}
