<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault20f1c655cfe83d9f84d5139f1f881caa extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('provider_authorizations')
            ->addIndex(['organization_id'], [
                'name'   => 'provider_authorizations_organization_id_idx',
                'unique' => false,
            ])
            ->addIndex(['source_oauth_account_id'], [
                'name'   => 'provider_authorizations_source_oauth_account_id_idx',
                'unique' => false,
            ])
            ->update()
        ;
        $this->table('sources')
            ->addIndex(['organization_id', 'canonical_url'], [
                'name'   => 'sources_organization_canonical_url_idx',
                'unique' => false,
            ])
            ->addIndex(['provider_authorization_id'], [
                'name'   => 'sources_provider_authorization_id_idx',
                'unique' => false,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('sources')
            ->dropIndex(['organization_id', 'canonical_url'])
            ->dropIndex(['provider_authorization_id'])
            ->update()
        ;
        $this->table('provider_authorizations')
            ->dropIndex(['organization_id'])
            ->dropIndex(['source_oauth_account_id'])
            ->update()
        ;
    }
}
