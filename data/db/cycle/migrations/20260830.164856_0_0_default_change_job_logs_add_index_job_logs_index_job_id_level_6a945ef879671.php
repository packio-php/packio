<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault83bd39e81760747ace19336faf61af4d extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('job_logs')
            ->addIndex(['job_id', 'level'], [
                'name'   => 'job_logs_index_job_id_level_6a945ef879671',
                'unique' => false,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('job_logs')
            ->dropIndex(['job_id', 'level'])
            ->update()
        ;
    }
}
