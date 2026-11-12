<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault14d0fde090388b187e65dad09805392c extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('packages')
            ->addIndex(['organization_id', 'name'], [
                'name'   => 'packages_index_organization_id_name_6a0f52171e5fc',
                'unique' => true,
            ])
            ->update()
        ;
        $this->table('releases')
            ->addIndex(['organization_id', 'package_name', 'version'], [
                'name'   => '2fa6e0738c7f4751b8daed9ef6ec0c40',
                'unique' => true,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('releases')
            ->dropIndex(['organization_id', 'package_name', 'version'])
            ->update()
        ;
        $this->table('packages')
            ->dropIndex(['organization_id', 'name'])
            ->update()
        ;
    }
}
