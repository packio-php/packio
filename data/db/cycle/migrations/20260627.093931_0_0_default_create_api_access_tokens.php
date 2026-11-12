<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefaultCd44ff615ebed8655c2d452b09802e7f extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('api_access_tokens')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 36,
            ])
            ->addColumn('user_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addColumn('name', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addColumn('token_hash', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addColumn('token_prefix', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addColumn('scopes', 'json', [
                'nullable'     => false,
                'defaultValue' => null,
            ])
            ->addColumn('last_used_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
            ])
            ->addColumn('expires_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
            ])
            ->addColumn('revoked_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => null,
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;
    }

    public function down(): void
    {
        $this->table('api_access_tokens')->drop();
    }
}
