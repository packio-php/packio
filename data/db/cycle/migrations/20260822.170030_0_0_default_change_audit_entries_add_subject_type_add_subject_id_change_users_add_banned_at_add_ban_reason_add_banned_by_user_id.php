<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault00ae3d752f1b20e854848db6a5ed35a9 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('audit_entries')
            ->addColumn('subject_type', 'string', [
                'nullable'     => false,
                'defaultValue' => 'legacy',
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('subject_id', 'string', [
                'nullable'     => false,
                'defaultValue' => '',
                'size'         => 255,
                'comment'      => '',
            ])
            ->update()
        ;
        // Existing audit records predate explicit subjects. Retain them as queryable legacy
        // records and use their UUID as a stable, non-empty subject identifier.
        $this->database()->execute("UPDATE audit_entries SET subject_id = uuid::text WHERE subject_id = ''");
        $this->database()->execute('ALTER TABLE audit_entries ALTER COLUMN subject_type DROP DEFAULT');
        $this->database()->execute('ALTER TABLE audit_entries ALTER COLUMN subject_id DROP DEFAULT');
        // Cycle's schema generator cannot register an index until newly declared columns
        // exist in the inspected database. Keep the schema declaration as the source of
        // truth and add its composite lookup index after those generated columns.
        $this->table('audit_entries')
            ->addIndex(['subject_type', 'subject_id', 'occurred_at', 'uuid'], [
                'name' => 'audit_entries_subject_recent_idx',
            ])
            ->update()
        ;
        $this->table('users')
            ->addColumn('banned_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('ban_reason', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 500,
                'comment'      => '',
            ])
            ->addColumn('banned_by_user_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('audit_entries')
            ->dropIndex(['subject_type', 'subject_id', 'occurred_at', 'uuid'])
            ->update()
        ;
        $this->table('users')
            ->dropColumn('banned_at')
            ->dropColumn('ban_reason')
            ->dropColumn('banned_by_user_id')
            ->update()
        ;
        $this->table('audit_entries')
            ->dropColumn('subject_type')
            ->dropColumn('subject_id')
            ->update()
        ;
    }
}
