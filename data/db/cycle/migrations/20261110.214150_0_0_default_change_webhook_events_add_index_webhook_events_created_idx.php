<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault01042cd97c4291be0bfbd8e313ac5850 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('webhook_events')
            ->addIndex(['created_at'], [
                'name'   => 'webhook_events_created_idx',
                'unique' => false,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('webhook_events')
            ->dropIndex(['created_at'])
            ->update()
        ;
    }
}
