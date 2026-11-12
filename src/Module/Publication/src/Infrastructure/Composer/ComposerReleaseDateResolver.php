<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Composer;

use Composer\Config;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryFactory;
use Composer\Util\Filesystem;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;

use function array_filter;
use function array_unique;
use function array_values;
use function bin2hex;
use function count;
use function is_dir;
use function ltrim;
use function mkdir;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;
use function trim;

final readonly class ComposerReleaseDateResolver implements ComposerReleaseDateResolverInterface
{
    /**
     * @param array<string, mixed> $source
     */
    public function resolve(array $source, ?string $accessToken = null): ComposerReleaseDateMap
    {
        $fallbackReleasedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $bufferIO           = new BufferIO();
        $repositoryUrl      = $this->repositoryUrl($source);

        // GitDriver clones a mirror into cache-vcs-dir; a per-call temp dir keeps the shared cache from growing forever.
        $dir = sys_get_temp_dir() . '/packio-composer-vcs-' . bin2hex(random_bytes(8));
        if (! mkdir($dir, 0o700) && ! is_dir($dir)) {
            throw new RuntimeException(sprintf('Could not create Composer VCS cache directory "%s".', $dir));
        }

        try {
            $this->configureAuthentication($bufferIO, $source, $accessToken);
            $config               = $this->config($bufferIO, $repositoryUrl, $dir);
            $repositoryRepository = RepositoryFactory::createRepo(
                $bufferIO,
                $config,
                [
                    'type' => 'vcs',
                    'url'  => $repositoryUrl,
                ],
                RepositoryFactory::manager($bufferIO, $config),
            );

            $dates = [];
            foreach ($repositoryRepository->getPackages() as $basePackage) {
                $this->indexPackage($dates, $basePackage, $fallbackReleasedAt);
            }

            if ([] === $dates) {
                throw new RuntimeException(sprintf('Composer did not resolve any package versions from "%s".', $repositoryUrl));
            }

            return new ComposerReleaseDateMap($dates, $fallbackReleasedAt);
        } finally {
            new Filesystem()->removeDirectory($dir);
        }
    }

    /**
     * @param array<string, string> $dates
     */
    private function indexPackage(array &$dates, PackageInterface $package, DateTimeImmutable $fallbackReleasedAt): void
    {
        $releasedAt = $this->formatDate($package->getReleaseDate() ?? $fallbackReleasedAt);
        $reference  = $package->getDistReference()
            ?? $package->getSourceReference()
            ?? $package->getPrettyVersion();

        $keys = [
            $package->getVersion(),
            $package->getPrettyVersion(),
            ltrim($package->getPrettyVersion(), 'v'),
            $package->getSourceReference(),
            $package->getDistReference(),
            $reference,
            sprintf('%s:%s', $package->getVersion(), $reference),
            sprintf('%s:%s', $package->getPrettyVersion(), $reference),
            sprintf('%s:%s', ltrim($package->getPrettyVersion(), 'v'), $reference),
        ];

        foreach (array_values(array_unique(array_filter($keys))) as $key) {
            $dates[$key] = $releasedAt;
        }
    }

    private function formatDate(DateTimeInterface $dateTime): string
    {
        return new DateTimeImmutable($dateTime->format(DATE_ATOM))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM)
        ;
    }

    private function config(BufferIO $bufferIO, string $repositoryUrl, string $vcsCacheDir): Config
    {
        $config = Factory::createConfig($bufferIO);
        $config->merge([
            'config'       => [
                'cache-vcs-dir' => $vcsCacheDir,
            ],
            'repositories' => [
                [
                    'type' => 'vcs',
                    'url'  => $repositoryUrl,
                ],
            ],
        ]);

        return $config;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function configureAuthentication(BufferIO $bufferIO, array $source, ?string $accessToken): void
    {
        $token = trim((string) $accessToken);
        if ('' === $token) {
            return;
        }

        match ((string) ($source['provider'] ?? '')) {
            'github'    => $bufferIO->setAuthentication('github.com', $token, 'x-oauth-basic'),
            'gitlab'    => $bufferIO->setAuthentication('gitlab.com', $token, 'oauth2'),
            'bitbucket' => $bufferIO->setAuthentication('bitbucket.org', 'x-token-auth', $token),
            default     => $bufferIO->setAuthentication('codeberg.org', $token, 'x-oauth-basic'),
        };
    }

    /**
     * @param array<string, mixed> $source
     */
    private function repositoryUrl(array $source): string
    {
        $provider = (string) ($source['provider'] ?? '');

        $url = match ($provider) {
            'github'             => sprintf('https://github.com/%s/%s', $source['owner'] ?? '', $source['repository'] ?? ''),
            'gitlab'             => sprintf('https://gitlab.com/%s/%s', $source['owner'] ?? '', $source['repository'] ?? ''),
            'bitbucket'          => sprintf('https://bitbucket.org/%s/%s', $source['owner'] ?? '', $source['repository'] ?? ''),
            'codeberg'           => sprintf('https://codeberg.org/%s/%s', $source['owner'] ?? '', $source['repository'] ?? ''),
            default              => '',
        };

        $url                        = trim($url);
        $missingProviderCoordinates = count(array_filter([$source['owner'] ?? null, $source['repository'] ?? null])) < 2;

        if ('' === $url || $missingProviderCoordinates) {
            throw new RuntimeException('Source repository URL could not be resolved for Composer metadata.');
        }

        return $url;
    }
}
