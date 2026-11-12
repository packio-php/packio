<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefaultA573345973967062a28904924ca9d9fb extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('packages')
            ->renameColumn('install_count', 'download_count')
            ->update()
        ;
        $this->table('releases')
            ->renameColumn('install_count', 'download_count')
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('releases')
            ->renameColumn('download_count', 'install_count')
            ->update()
        ;
        $this->table('packages')
            ->renameColumn('download_count', 'install_count')
            ->update()
        ;
    }
}
