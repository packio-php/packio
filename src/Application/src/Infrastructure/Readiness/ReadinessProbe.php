<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Readiness;

use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use PDO;

use function array_diff;
use function array_map;
use function basename;
use function dirname;
use function explode;
use function file_exists;
use function glob;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_executable;
use function is_file;
use function is_readable;
use function is_string;
use function is_writable;
use function str_replace;

/**
 * @phpstan-type ProbeConfig array{dsn:string,user:?string,password:?string,schemas:list<string>,schema_file:string,migrations_dir:string,migrations_table:string,archive:?string,cache:list<string>,settings:list<string>}
 */
final class ReadinessProbe
{
    /** @param ProbeConfig $config */
    public static function check(array $config): bool
    {
        if (! is_file($config['schema_file']) || ! is_readable($config['schema_file'])) {
            return false;
        }

        if (null !== $config['archive'] && (! is_dir($config['archive']) || ! is_readable($config['archive']) || ! is_writable($config['archive']) || ! is_executable($config['archive']))) {
            return false;
        }

        foreach ($config['cache'] as $directory) {
            // Lazy cache subdirectories may be created later by normal requests.
            // Check their nearest existing parent without creating anything here.
            while (! file_exists($directory) && dirname($directory) !== $directory) {
                $directory = dirname($directory);
            }

            if (! is_dir($directory) || ! is_readable($directory) || ! is_writable($directory) || ! is_executable($directory)) {
                return false;
            }
        }
        $pdo = new PDO($config['dsn'] . ';connect_timeout=1', $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $quote = static fn (string $name): string => '"' . str_replace('"', '""', $name) . '"';
        $pdo->exec('SET default_transaction_read_only = on');
        $pdo->exec('SET statement_timeout = 500');
        $pdo->exec('SET lock_timeout = 500');
        $pdo->exec('SET search_path TO ' . implode(',', array_map($quote, $config['schemas'])));
        $migrationQuery = $pdo->query('SELECT migration FROM ' . $quote($config['migrations_table']) . ' WHERE time_executed IS NOT NULL');
        if (false === $migrationQuery) {
            return false;
        }
        $executed = $migrationQuery->fetchAll(PDO::FETCH_COLUMN);
        $compiled = require $config['schema_file'];
        if (! is_array($compiled) || [] === $compiled) {
            return false;
        }
        $schema = new Schema($compiled);
        foreach ($schema->getRoles() as $role) {
            $table   = $schema->define($role, SchemaInterface::TABLE);
            $columns = $schema->define($role, SchemaInterface::COLUMNS);
            if (! is_string($table) || ! is_array($columns) || [] === $columns) {
                return false;
            }

            foreach ($columns as $column) {
                if (! is_string($column)) {
                    return false;
                }
            }
            $pdo->query('SELECT ' . implode(',', array_map($quote, $columns)) . ' FROM ' . $quote($table) . ' LIMIT 0');
        }

        $files = glob($config['migrations_dir'] . '/*.php');
        if (false === $files || [] === $files) {
            return false;
        }

        foreach ($files as $file) {
            $name = explode('_', basename($file, '.php'), 3)[2] ?? '';
            if (! in_array($name, $executed, true)) {
                return false;
            }
        }
        $settingsQuery = $pdo->query('SELECT setting_key FROM project_settings');
        if (false === $settingsQuery) {
            return false;
        }
        $settings = $settingsQuery->fetchAll(PDO::FETCH_COLUMN);

        return [] === array_diff($config['settings'], $settings);
    }
}
