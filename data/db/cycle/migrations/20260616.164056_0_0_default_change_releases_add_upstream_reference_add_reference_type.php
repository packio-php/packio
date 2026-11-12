<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault5fc6de0e2d52d67c81b8008620e17492 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('releases')
            ->addColumn('upstream_reference', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addColumn('reference_type', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('releases')
            ->dropColumn('upstream_reference')
            ->dropColumn('reference_type')
            ->update()
        ;
    }
}
