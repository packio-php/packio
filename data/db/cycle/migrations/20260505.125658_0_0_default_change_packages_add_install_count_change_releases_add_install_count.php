<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class OrmDefaultC86886a5cd9a12961b300bccfd58bc25 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('packages')
            ->addColumn('install_count', 'integer', [
                'nullable'     => false,
                'defaultValue' => 0,
                'comment'      => '',
            ])
            ->update()
        ;
        $this->table('releases')
            ->addColumn('install_count', 'integer', [
                'nullable'     => false,
                'defaultValue' => 0,
                'comment'      => '',
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('releases')
            ->dropColumn('install_count')
            ->update()
        ;
        $this->table('packages')
            ->dropColumn('install_count')
            ->update()
        ;
    }
}
