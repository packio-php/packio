<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefaultBd0b06ac1157a32229a829df39f98aa4 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('organizations')
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
        $this->table('organizations')
            ->dropColumn('status')
            ->update()
        ;
    }
}
