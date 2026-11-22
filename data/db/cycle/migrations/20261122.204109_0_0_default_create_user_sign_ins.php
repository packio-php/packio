<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault0f6a11f116dbdbe0b12571d2c7fc699b extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('user_sign_ins')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('user_uuid', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('method', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('ip', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('user_agent', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;
    }

    public function down(): void
    {
        $this->table('user_sign_ins')->drop();
    }
}
