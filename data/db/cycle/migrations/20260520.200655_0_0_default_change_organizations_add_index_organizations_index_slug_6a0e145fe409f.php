<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault4e1133138c5cf43861162ea830b5f954 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('organizations')
            ->addIndex(['slug'], [
                'name'   => 'organizations_index_slug_6a0e145fe409f',
                'unique' => true,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('organizations')
            ->dropIndex(['slug'])
            ->update()
        ;
    }
}
