<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault4b02c6838a13097cbdff2f9a33bc5b4d extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('jobs')
            ->addIndex(['status', 'scheduled_at'], [
                'name'   => 'jobs_index_status_scheduled_at_6ada5954a65c8',
                'unique' => false,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('jobs')
            ->dropIndex(['status', 'scheduled_at'])
            ->update()
        ;
    }
}
