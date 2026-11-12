<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Application\Command;

use const STREAM_IPPROTO_IP;
use const STREAM_PF_UNIX;
use const STREAM_SOCK_STREAM;

use Cycle\Database\DatabaseProviderInterface;
use Packio\Organization\Api\Exception\OrganizationMembershipException;
use Packio\Organization\Application\Command\OrganizationCommandService;
use PackioTest\Support\Base\ForkedChildProcess;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;

use function array_filter;
use function array_values;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function fread;
use function function_exists;
use function fwrite;
use function is_array;
use function is_string;
use function pcntl_fork;
use function pcntl_waitpid;
use function pcntl_wexitstatus;
use function pcntl_wifexited;
use function random_int;
use function sort;
use function sprintf;
use function stream_socket_pair;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[Group('slow')]
final class OrganizationMemberRoleConcurrencyTest extends IntegrationTestCase
{
    use ForkedChildProcess;

    #[Test]
    public function concurrentOwnerDemotionsKeepOneOwner(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $commandService = $this->getService(OrganizationCommandService::class);
        self::assertInstanceOf(OrganizationCommandService::class, $commandService);

        $organization       = $commandService->registerOrganization('Concurrent Owners ' . random_int(10000, 99999));
        $organizationId     = $organization->getIdentifier()->toString();
        $organizationMember = $commandService->addMember($organizationId, 'owner-a', 'owner');
        $secondOwner        = $commandService->addMember($organizationId, 'owner-b', 'owner');

        $this->disconnectDatabaseBeforeFork();

        $errorPath = tempnam(sys_get_temp_dir(), 'packio-owner-demotion-errors-');
        self::assertIsString($errorPath);

        $statuses = $this->runConcurrently(
            [
                $organizationMember->getIdentifier()->toString(),
                $secondOwner->getIdentifier()->toString(),
            ],
            $errorPath,
            fn (OrganizationCommandService $organizationCommandService, string $memberId): mixed => $organizationCommandService->updateMemberRole(
                $organizationId,
                $memberId,
                'member',
            ),
        );

        sort($statuses);

        $childErrors = $this->childErrors($errorPath);
        @unlink($errorPath);

        self::assertSame([0, 2], $statuses, $childErrors);

        $roles  = $this->organizationMemberRoles($organizationId);
        $owners = array_values(array_filter(
            $roles,
            static fn (string $role): bool => 'owner' === $role,
        ));
        $regularMembers = array_values(array_filter(
            $roles,
            static fn (string $role): bool => 'member' === $role,
        ));

        self::assertCount(1, $owners);
        self::assertCount(1, $regularMembers);
    }

    #[Test]
    public function concurrentOwnerRemovalsKeepOneOwner(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $commandService = $this->getService(OrganizationCommandService::class);
        self::assertInstanceOf(OrganizationCommandService::class, $commandService);

        $organization   = $commandService->registerOrganization('Concurrent Removals ' . random_int(10000, 99999));
        $organizationId = $organization->getIdentifier()->toString();
        $firstOwner     = $commandService->addMember($organizationId, 'owner-a', 'owner')->getIdentifier()->toString();
        $secondOwner    = $commandService->addMember($organizationId, 'owner-b', 'owner')->getIdentifier()->toString();

        $this->disconnectDatabaseBeforeFork();

        $errorPath = tempnam(sys_get_temp_dir(), 'packio-owner-removal-errors-');
        self::assertIsString($errorPath);

        $statuses = $this->runConcurrently(
            [$secondOwner, $firstOwner],
            $errorPath,
            fn (OrganizationCommandService $organizationCommandService, string $memberId): mixed => $organizationCommandService->removeMember(
                $organizationId,
                $memberId,
            ),
        );

        sort($statuses);

        $childErrors = $this->childErrors($errorPath);
        @unlink($errorPath);

        self::assertSame([0, 2], $statuses, $childErrors);
        self::assertSame(['owner'], $this->organizationMemberRoles($organizationId));
    }

    /**
     * @param list<string>                                        $memberIds
     * @param callable(OrganizationCommandService, string): mixed $action
     *
     * @return list<int>
     */
    private function runConcurrently(array $memberIds, string $errorPath, callable $action): array
    {
        $children = [];

        foreach ($memberIds as $memberId) {
            $releaseSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertIsArray($releaseSockets);

            $readySockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertIsArray($readySockets);

            $pid = pcntl_fork();
            self::assertNotFalse($pid);

            if (0 === $pid) {
                fclose($releaseSockets[0]);
                fclose($readySockets[0]);

                fwrite($readySockets[1], '1');
                fclose($readySockets[1]);

                @fread($releaseSockets[1], 1);
                fclose($releaseSockets[1]);

                $this->runInChild($memberId, $errorPath, $action);
            }

            fclose($releaseSockets[1]);
            fclose($readySockets[1]);
            $children[] = [
                'pid'           => $pid,
                'readySocket'   => $readySockets[0],
                'releaseSocket' => $releaseSockets[0],
            ];
        }

        foreach ($children as $child) {
            self::assertSame('1', fread($child['readySocket'], 1));
            fclose($child['readySocket']);
        }

        foreach ($children as $child) {
            fwrite($child['releaseSocket'], '1');
            fclose($child['releaseSocket']);
        }

        $statuses = [];
        foreach ($children as $child) {
            $status = 0;
            pcntl_waitpid($child['pid'], $status);
            if (! pcntl_wifexited($status)) {
                $statuses[] = 1;

                continue;
            }

            $exitStatus = pcntl_wexitstatus($status);
            $statuses[] = false === $exitStatus ? 1 : $exitStatus;
        }

        return $statuses;
    }

    private function runInChild(string $memberId, string $errorPath, callable $action): never
    {
        try {
            $commandService = $this->getService(OrganizationCommandService::class);
            if (! $commandService instanceof OrganizationCommandService) {
                throw new RuntimeException('OrganizationCommandService is not configured.');
            }

            $action($commandService, $memberId);

            exit(0);
        } catch (OrganizationMembershipException) {
            exit(2);
        } catch (Throwable $throwable) {
            file_put_contents(
                $errorPath,
                sprintf("%s: %s\n", $throwable::class, $throwable->getMessage()),
                FILE_APPEND,
            );

            exit(1);
        }
    }

    /**
     * @return list<string>
     */
    private function organizationMemberRoles(string $organizationId): array
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        $rows = $databaseProvider
            ->database()
            ->select('role')
            ->from('organization_members')
            ->where('organization_id', $organizationId)
            ->fetchAll()
        ;

        $roles = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (! isset($row['role'])) {
                continue;
            }

            if (! is_string($row['role'])) {
                continue;
            }

            $roles[] = $row['role'];
        }

        return $roles;
    }

    private function childErrors(string $errorPath): string
    {
        $contents = file_get_contents($errorPath);
        if (false === $contents || '' === $contents) {
            return 'Expected exactly one successful demotion and one last-owner rejection.';
        }

        return $contents;
    }
}
