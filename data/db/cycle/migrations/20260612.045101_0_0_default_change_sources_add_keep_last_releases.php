<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefaultFae0d665bac873159ea62489351ed58e extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('sources')
            ->addColumn('keep_last_releases', 'integer', [
                'nullable'     => false,
                'defaultValue' => 0,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('sources')
            ->dropColumn('keep_last_releases')
            ->update()
        ;
    }
}
