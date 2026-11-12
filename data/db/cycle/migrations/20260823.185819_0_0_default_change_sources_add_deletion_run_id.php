<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault38a9d8059f625d0d600fb61ac0688451 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('sources')
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
        $this->table('sources')
            ->dropColumn('deletion_run_id')
            ->update()
        ;
    }
}
