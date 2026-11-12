<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefaultFe936777b888656801a97032ef81600d extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('organizations')
            ->addColumn('deletion_run_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 36,
                'comment'      => '',
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('organizations')
            ->dropColumn('deletion_run_id')
            ->update()
        ;
    }
}
