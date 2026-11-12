<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault4b9e4d323fc109ebfa1b663e78e82be3 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('webhook_events')
            ->addIndex(['source_id', 'created_at'], [
                'name'   => 'webhook_events_source_created_idx',
                'unique' => false,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('webhook_events')
            ->dropIndex(['source_id', 'created_at'])
            ->update()
        ;
    }
}
