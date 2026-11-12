<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault420acb0f65e09c5673c3dc0fa6862989 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('audit_entries')
            ->addColumn('operation_key', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('audit_entries')
            ->dropColumn('operation_key')
            ->update()
        ;
    }
}
