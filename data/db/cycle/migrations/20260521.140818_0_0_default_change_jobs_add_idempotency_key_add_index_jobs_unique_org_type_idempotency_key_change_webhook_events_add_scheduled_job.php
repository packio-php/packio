<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault069f214095846df58d1fdf29f0b4b141 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('jobs')
            ->addColumn('idempotency_key', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addIndex(['organization_id', 'type', 'idempotency_key'], [
                'name'   => 'jobs_unique_org_type_idempotency_key',
                'unique' => true,
            ])
            ->update()
        ;
        $this->table('webhook_events')
            ->addColumn('scheduled_job_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
            ])
            ->addColumn('reprocess_count', 'integer', [
                'nullable'     => false,
                'defaultValue' => 0,
            ])
            ->addIndex(['source_id', 'provider', 'delivery_id'], [
                'name'   => 'webhook_events_unique_source_provider_delivery',
                'unique' => true,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('webhook_events')
            ->dropIndex(['source_id', 'provider', 'delivery_id'])
            ->dropColumn('scheduled_job_id')
            ->dropColumn('reprocess_count')
            ->update()
        ;
        $this->table('jobs')
            ->dropIndex(['organization_id', 'type', 'idempotency_key'])
            ->dropColumn('idempotency_key')
            ->update()
        ;
    }
}
