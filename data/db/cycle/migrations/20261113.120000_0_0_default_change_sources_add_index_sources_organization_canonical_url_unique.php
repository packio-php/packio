<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;
use RuntimeException;

class OrmDefault7c1f0a9d5e3b4c2f8a6d1e0b9c4f7a23 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        // Duplicates can only come from two concurrent "add source" requests. Which copy owns the packages is not
        // knowable here, so the operator removes the extra source instead of the migration guessing.
        $duplicates = (int) $this->database()->query(<<<'SQL'
            SELECT COUNT(*) FROM (
                SELECT 1 FROM sources GROUP BY organization_id, canonical_url HAVING COUNT(*) > 1
            ) AS duplicates
            SQL)->fetchColumn();
        if ($duplicates > 0) {
            throw new RuntimeException(
                $duplicates . ' repositories are added more than once in the same organization. Delete the extra sources, then run the migration again. '
                . 'List them: SELECT organization_id, canonical_url, COUNT(*) FROM sources GROUP BY organization_id, canonical_url HAVING COUNT(*) > 1',
            );
        }

        $this->table('sources')
            ->dropIndex(['organization_id', 'canonical_url'])
            ->addIndex(['organization_id', 'canonical_url'], [
                'name'   => 'sources_organization_canonical_url_unique',
                'unique' => true,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('sources')
            ->dropIndex(['organization_id', 'canonical_url'])
            ->addIndex(['organization_id', 'canonical_url'], [
                'name'   => 'sources_organization_canonical_url_idx',
                'unique' => false,
            ])
            ->update()
        ;
    }
}
