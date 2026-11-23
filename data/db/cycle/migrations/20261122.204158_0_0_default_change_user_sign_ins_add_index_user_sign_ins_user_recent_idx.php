<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault5ecb725fdbc232d4604f78d4edff0cc5 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('user_sign_ins')
            ->addIndex(['user_uuid', 'created_at'], [
                'name'   => 'user_sign_ins_user_recent_idx',
                'unique' => false,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('user_sign_ins')
            ->dropIndex(['user_uuid', 'created_at'])
            ->update()
        ;
    }
}
