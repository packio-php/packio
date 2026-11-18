<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault7d3f0a91c5e84b2fa6c1e0d9b8a47352 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('audit_entries')
            ->addIndex(['occurred_at', 'uuid'], [
                'name'   => 'audit_entries_recent_idx',
                'unique' => false,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('audit_entries')
            ->dropIndex(['occurred_at', 'uuid'])
            ->update()
        ;
    }
}
