<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault6c35ea8884c5c97d3a47314ab141025b extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('user_sign_ins')
            ->addIndex(['created_at'], [
                'name'   => 'user_sign_ins_created_at_idx',
                'unique' => false,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('user_sign_ins')
            ->dropIndex(['created_at'])
            ->update()
        ;
    }
}
