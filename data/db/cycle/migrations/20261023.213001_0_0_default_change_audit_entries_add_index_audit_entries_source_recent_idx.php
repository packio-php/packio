<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault01da36c176c63905b5eb752792c6dd01 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('audit_entries')
            ->addIndex(['organization_id', 'source_id', 'occurred_at', 'uuid'], [
                'name'   => 'audit_entries_source_recent_idx',
                'unique' => false,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('audit_entries')
            ->dropIndex(['organization_id', 'source_id', 'occurred_at', 'uuid'])
            ->update()
        ;
    }
}
