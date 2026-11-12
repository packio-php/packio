<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefaultD1551af36b627969e65fd1080cab45bd extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('source_oauth_accounts')
            ->alterColumn('access_token', 'text', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->alterColumn('refresh_token', 'text', [
                'nullable'     => true,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->update()
        ;
        $this->table('user_oauth_accounts')
            ->alterColumn('access_token', 'text', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->alterColumn('refresh_token', 'text', [
                'nullable'     => true,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('user_oauth_accounts')
            ->alterColumn('access_token', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->alterColumn('refresh_token', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->update()
        ;
        $this->table('source_oauth_accounts')
            ->alterColumn('access_token', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->alterColumn('refresh_token', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->update()
        ;
    }
}
