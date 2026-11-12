<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefaultE62a461e72d33209363a3c6efc0c45ad extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('source_oauth_accounts')
            ->addColumn('credential_key', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 64,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('source_oauth_accounts')
            ->dropColumn('credential_key')
            ->update()
        ;
    }
}
