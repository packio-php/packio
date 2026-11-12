<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault7d362231d27dcdc72d7cfb560d93bb3a extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('sources')
            ->addColumn('status', 'string', [
                'nullable'     => false,
                'defaultValue' => 'active',
                'size'         => 255,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('sources')
            ->dropColumn('status')
            ->update()
        ;
    }
}
