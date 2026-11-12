<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Override;
use Packio\Identity\Domain\Entity\EmailVerificationCode;
use Packio\Identity\Domain\Repository\EmailVerificationCodeRepositoryInterface;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function is_object;

/**
 * @extends AbstractWriteRepository<EmailVerificationCode>
 */
final class EmailVerificationCodeRepository extends AbstractWriteRepository implements EmailVerificationCodeRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string TABLE_NAME   = 'email_verification_codes';
    private const string ENTITY_CLASS = EmailVerificationCode::class;

    /**
     * @throws RepositoryPersistException
     */
    public function save(EmailVerificationCode $emailVerificationCode): void
    {
        $this->persistAndFlush($emailVerificationCode);
    }

    #[Override]
    public function delete(EntityInterface $code, bool $cascade = true, bool $flush = false): void
    {
        $identifier = $code->getIdentifier();
        $uuid       = is_object($identifier) ? $identifier->toString() : (string) $identifier;
        $this->database()
            ->delete(self::TABLE_NAME, [
                'uuid' => $uuid,
            ])
            ->run()
        ;
    }

    public function findActiveByUserUuid(string $userUuid): ?EmailVerificationCode
    {
        return $this->findOne([
            'userUuid'   => $userUuid,
            'verifiedAt' => null,
        ]);
    }

    public function deleteExpired(): int
    {
        $now = new Chronos();

        return $this->database()
            ->delete(self::TABLE_NAME, [
                'verified_at' => null,
                'expires_at'  => [
                    '<' => $now,
                ],
            ])
            ->run()
        ;
    }
}
