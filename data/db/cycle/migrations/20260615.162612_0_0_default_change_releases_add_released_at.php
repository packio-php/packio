<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault14e803bded3c64fc0aa3a47daa052463 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('releases')
            ->addColumn('released_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('releases')
            ->dropColumn('released_at')
            ->update()
        ;
    }
}
