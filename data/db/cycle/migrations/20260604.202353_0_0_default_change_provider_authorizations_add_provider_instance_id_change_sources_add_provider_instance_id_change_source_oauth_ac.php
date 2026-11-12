<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault30d00bb26793eea67dfe92f9241ea760 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('provider_authorizations')
            ->addColumn('provider_instance_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 36,
            ])
            ->update()
        ;
        $this->table('sources')
            ->addColumn('provider_instance_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 36,
            ])
            ->update()
        ;
        $this->table('source_oauth_accounts')
            ->addColumn('provider_instance_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 36,
            ])
            ->addColumn('provider_base_url', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->update()
        ;
        $this->table('source_provider_instances')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 36,
            ])
            ->addColumn('organization_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addColumn('provider_type', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addColumn('label', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addColumn('base_url', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addColumn('client_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addColumn('client_secret', 'text', [
                'nullable'     => true,
                'defaultValue' => null,
            ])
            ->addColumn('auth_mode', 'string', [
                'nullable'     => false,
                'defaultValue' => 'oauth2_and_token',
                'size'         => 255,
            ])
            ->addColumn('status', 'string', [
                'nullable'     => false,
                'defaultValue' => 'active',
                'size'         => 255,
            ])
            ->addColumn('metadata', 'json', [
                'nullable'     => false,
                'defaultValue' => '[]',
            ])
            ->addColumn('created_by_user_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => null,
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;
    }

    public function down(): void
    {
        $this->table('source_provider_instances')->drop();
        $this->table('source_oauth_accounts')
            ->dropColumn('provider_instance_id')
            ->dropColumn('provider_base_url')
            ->update()
        ;
        $this->table('sources')
            ->dropColumn('provider_instance_id')
            ->update()
        ;
        $this->table('provider_authorizations')
            ->dropColumn('provider_instance_id')
            ->update()
        ;
    }
}
