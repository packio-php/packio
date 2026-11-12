<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault5ed7ab1bb79ef592c5fe380cd812392f extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('users')
            ->addIndex(['email', 'uuid'], [
                'name'   => 'users_email_uuid_idx',
                'unique' => false,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('users')
            ->dropIndex(['email', 'uuid'])
            ->update()
        ;
    }
}
