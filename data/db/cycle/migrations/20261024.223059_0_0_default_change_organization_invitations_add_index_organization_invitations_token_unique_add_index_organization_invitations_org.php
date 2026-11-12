<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefaultF731196e8b9f8db06af9ef57310d509a extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        // Keep the owner row, else the earliest, before the unique index is built.
        $this->database()->execute(<<<'SQL'
            DELETE FROM organization_members WHERE uuid IN (
                SELECT uuid FROM (
                    SELECT uuid, ROW_NUMBER() OVER (
                        PARTITION BY organization_id, user_id
                        ORDER BY (role = 'owner') DESC, created_at ASC, uuid ASC
                    ) AS rn FROM organization_members
                ) ranked WHERE rn > 1
            )
            SQL);
        $this->table('organization_invitations')
            ->addIndex(['token'], [
                'name'   => 'organization_invitations_token_unique',
                'unique' => true,
            ])
            ->addIndex(['organization_id'], [
                'name'   => 'organization_invitations_organization_id_idx',
                'unique' => false,
            ])
            ->update()
        ;
        $this->table('organization_members')
            ->addIndex(['organization_id', 'user_id'], [
                'name'   => 'organization_members_organization_user_unique',
                'unique' => true,
            ])
            ->addIndex(['user_id'], [
                'name'   => 'organization_members_user_id_idx',
                'unique' => false,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('organization_members')
            ->dropIndex(['organization_id', 'user_id'])
            ->dropIndex(['user_id'])
            ->update()
        ;
        $this->table('organization_invitations')
            ->dropIndex(['token'])
            ->dropIndex(['organization_id'])
            ->update()
        ;
    }
}
