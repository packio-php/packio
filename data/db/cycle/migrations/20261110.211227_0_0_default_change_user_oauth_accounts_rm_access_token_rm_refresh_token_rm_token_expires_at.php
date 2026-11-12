<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefaultE416bbe1709bdaa5c0fc775362bea301 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('user_oauth_accounts')
            ->dropColumn('access_token')
            ->dropColumn('refresh_token')
            ->dropColumn('token_expires_at')
            ->update()
        ;
    }

    public function down(): void
    {
        // The dropped login OAuth tokens are gone for good: the columns come back empty (access_token as '' because the old entity needs a non-null string).
        $this->table('user_oauth_accounts')
            ->addColumn('access_token', 'text', [
                'nullable'     => false,
                'defaultValue' => '',
                'comment'      => '',
            ])
            ->addColumn('refresh_token', 'text', [
                'nullable'     => true,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('token_expires_at', 'timestamp', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->update()
        ;
    }
}
