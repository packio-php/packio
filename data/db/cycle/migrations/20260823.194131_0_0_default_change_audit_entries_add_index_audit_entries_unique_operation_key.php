<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault31a40f0f810422895a987c6cc5b15d75 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('audit_entries')
            ->addIndex(['operation_key'], [
                'name'   => 'audit_entries_unique_operation_key',
                'unique' => true,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('audit_entries')
            ->dropIndex(['operation_key'])
            ->update()
        ;
    }
}
