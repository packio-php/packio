<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault512b2269e38312b1a548faca6c5b406b extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('source_provider_instances')
            ->addIndex(['organization_id', 'provider_type', 'base_url'], [
                'name'   => 'source_provider_instances_unique_org_provider_base',
                'unique' => true,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('source_provider_instances')
            ->dropIndex(['organization_id', 'provider_type', 'base_url'])
            ->update()
        ;
    }
}
