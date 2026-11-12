<?php

declare(strict_types=1);

use Spiral\Goridge\RPC\RPC;

require \dirname(__DIR__) . '/vendor/autoload.php';

// Check the supervised processes, not just whether RoadRunner is alive.
try {
    $workerCount = \filter_var(\getenv('PACKIO_WORKER_NUM') ?: '7', FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
        ],
    ]);
    if (false === $workerCount) {
        throw new RuntimeException('Invalid PACKIO_WORKER_NUM.');
    }

    $rpc      = RPC::create('tcp://127.0.0.1:6001');
    $expected = [
        'worker'                      => $workerCount,
        'worker-cleanup'              => 1,
        'email-verification-cleanup'  => 1,
        'password-reset-cleanup'      => 1,
        'source-oauth-refresh'        => 1,
    ];

    foreach ($expected as $name => $count) {
        $response = $rpc->call('service.Statuses', [
            'name' => $name,
        ]);
        $processes = $response['status'] ?? [];
        if (\count($processes) !== $count) {
            throw new RuntimeException(\sprintf('%s: expected %d processes.', $name, $count));
        }

        foreach ($processes as $process) {
            if (($process['pid'] ?? 0) <= 0 || null !== ($process['status'] ?? null)) {
                throw new RuntimeException(\sprintf('%s: a supervised process is unavailable.', $name));
            }
        }
    }
} catch (Throwable $error) {
    \fwrite(STDERR, $error->getMessage() . "\n");

    exit(1);
}
