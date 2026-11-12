<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class Orm805e078eef476805b437e50f20bc5ba1536b extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('api_access_tokens')
            ->addIndex(['token_hash'], [
                'name'   => 'api_access_tokens_unique_token_hash',
                'unique' => true,
            ])
            ->addIndex(['user_id'], [
                'name' => 'api_access_tokens_index_user_id',
            ])
            ->addIndex(['token_prefix'], [
                'name' => 'api_access_tokens_index_token_prefix',
            ])
            ->addIndex(['revoked_at', 'expires_at'], [
                'name' => 'api_access_tokens_index_revoked_expires',
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('api_access_tokens')
            ->dropIndex(['revoked_at', 'expires_at'])
            ->dropIndex(['token_prefix'])
            ->dropIndex(['user_id'])
            ->dropIndex(['token_hash'])
            ->update()
        ;
    }
}
