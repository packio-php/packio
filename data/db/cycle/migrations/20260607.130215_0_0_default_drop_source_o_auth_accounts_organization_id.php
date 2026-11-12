<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class Orm46b3498fbab4e24af5c59121b3e6249ab860 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('source_oauth_accounts')
            ->dropColumn('organization_id')
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('source_oauth_accounts')
            ->addColumn('organization_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->update()
        ;
    }
}
