<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault51ec11d5d11e7a0740bb15e5dd6cb956 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('source_oauth_accounts')
            ->addIndex(['credential_key'], [
                'name'   => 'source_oauth_accounts_unique_credential_key',
                'unique' => true,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('source_oauth_accounts')
            ->dropIndex(['credential_key'])
            ->update()
        ;
    }
}
