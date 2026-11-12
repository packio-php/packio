<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault3bfe03b54d2fb8317e2c12bbc56e1790 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        // Nullable first so existing rows can be backfilled, then made NOT NULL.
        $this->table('organization_invitations')
            ->addColumn('token_hash', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 64,
                'comment'      => '',
            ])
            ->update()
        ;
        // Rows written before this migration hold the plaintext token, so the lookup hash is computed in SQL.
        $this->database()->execute(<<<'SQL'
            UPDATE organization_invitations SET token_hash = encode(sha256(convert_to(token, 'UTF8')), 'hex') WHERE token_hash IS NULL
            SQL);
        // Tokens become non-deterministic ciphertext, so uniqueness moves from token to token_hash.
        $this->table('organization_invitations')
            ->alterColumn('token_hash', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 64,
                'comment'      => '',
            ])
            ->dropIndex(['token'])
            ->addIndex(['token_hash'], [
                'name'   => 'organization_invitations_token_hash_unique',
                'unique' => true,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        // Tokens already encrypted by the app or `organization:invitations:encrypt` stay ciphertext:
        // SQL cannot decrypt them, so their links stop working on the old code.
        $this->table('organization_invitations')
            ->dropIndex(['token_hash'])
            ->dropColumn('token_hash')
            ->addIndex(['token'], [
                'name'   => 'organization_invitations_token_unique',
                'unique' => true,
            ])
            ->update()
        ;
    }
}
