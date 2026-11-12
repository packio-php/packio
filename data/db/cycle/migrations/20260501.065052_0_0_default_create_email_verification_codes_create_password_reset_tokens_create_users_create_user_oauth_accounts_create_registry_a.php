<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class OrmDefaultAfb712ac88db5eb76de54fe34357e2b7 extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('email_verification_codes')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('version', 'integer', [
                'nullable'     => false,
                'defaultValue' => 1,
                'comment'      => '',
            ])
            ->addColumn('user_uuid', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('code_hash', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('expires_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('verified_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('password_reset_tokens')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('version', 'integer', [
                'nullable'     => false,
                'defaultValue' => 1,
                'comment'      => '',
            ])
            ->addColumn('user_uuid', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('selector', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('token_hash', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('expires_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('used_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('requested_ip', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('consumed_ip', 'string', [
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
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('users')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('email', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('display_name', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('password_hash', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('roles', 'json', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('email_verified_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('user_oauth_accounts')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('user_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider_user_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider_email', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider_username', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('access_token', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('refresh_token', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('token_expires_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('scopes', 'json', [
                'nullable'     => false,
                'defaultValue' => '[]',
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('registry_access_tokens')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('owner_user_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('organization_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('name', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('token_hash', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('masked_token', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('permissions', 'json', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('last_used_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('encrypted_token', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('organizations')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('slug', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('name', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('anonymous_access', 'boolean', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('active', 'boolean', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('organization_invitations')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('organization_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('invited_by_user_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('token', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('role', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('accepted_by_user_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('expires_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('accepted_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('revoked_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('organization_members')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('organization_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('user_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('role', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('sources')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('organization_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('canonical_url', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('display_name', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider_authorization_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider_resource_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider_config', 'json', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('default_ref', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('visibility', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('active', 'boolean', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('last_sync_status', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('last_successful_sync_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('last_failed_sync_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('last_sync_error', 'text', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('archived_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('provider_authorizations')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('organization_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('auth_strategy', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('status', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('account_name', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('configuration', 'json', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('source_oauth_account_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 36,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('source_oauth_accounts')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('user_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('organization_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider_user_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider_email', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider_username', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('access_token', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('refresh_token', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('token_expires_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('scopes', 'json', [
                'nullable'     => false,
                'defaultValue' => '[]',
                'comment'      => '',
            ])
            ->addColumn('is_active', 'boolean', [
                'nullable'     => false,
                'defaultValue' => 'true',
                'comment'      => '',
            ])
            ->addColumn('last_used_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('packages')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('organization_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('name', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('type', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('description', 'text', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('visibility', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('active', 'boolean', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('source_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('source_type', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('source_url', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('readme_html', 'text', [
                'nullable'     => true,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('releases')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('organization_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('package_name', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('version', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('reference', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('active', 'boolean', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('composer_data', 'json', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('source_data', 'json', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('dist_data', 'json', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('audit_entries')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('event', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('occurred_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('organization_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('source_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('package_name', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('actor_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('context', 'json', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('project_settings')
            ->addColumn('setting_key', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('setting_value', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('updated_by_user_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['setting_key'])
            ->create()
        ;

        $this->table('webhook_events')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('source_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('provider', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('delivery_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('event', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('status', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('payload_hash', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('message', 'text', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('processed_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('updated_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('jobs')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('version', 'integer', [
                'nullable'     => false,
                'defaultValue' => 1,
                'comment'      => '',
            ])
            ->addColumn('organization_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('type', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('payload', 'json', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('status', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('priority', 'integer', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('scheduled_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('started_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('completed_at', 'datetime', [
                'nullable'     => true,
                'defaultValue' => null,
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->addColumn('attempt_count', 'integer', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('max_attempts', 'integer', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('error_message', 'text', [
                'nullable'     => true,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('error_details', 'text', [
                'nullable'     => true,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('worker_id', 'string', [
                'nullable'     => true,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;

        $this->table('job_logs')
            ->addColumn('uuid', 'uuid', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('job_id', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('level', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('message', 'string', [
                'nullable'     => false,
                'defaultValue' => null,
                'size'         => 255,
                'comment'      => '',
            ])
            ->addColumn('context', 'json', [
                'nullable'     => false,
                'defaultValue' => null,
                'comment'      => '',
            ])
            ->addColumn('created_at', 'datetime', [
                'nullable'     => false,
                'defaultValue' => 'CURRENT_TIMESTAMP',
                'withTimezone' => false,
                'comment'      => '',
            ])
            ->setPrimaryKeys(['uuid'])
            ->create()
        ;
    }

    public function down(): void
    {
        $this->table('job_logs')->drop();
        $this->table('jobs')->drop();
        $this->table('webhook_events')->drop();
        $this->table('project_settings')->drop();
        $this->table('audit_entries')->drop();
        $this->table('releases')->drop();
        $this->table('packages')->drop();
        $this->table('source_oauth_accounts')->drop();
        $this->table('provider_authorizations')->drop();
        $this->table('sources')->drop();
        $this->table('organization_members')->drop();
        $this->table('organization_invitations')->drop();
        $this->table('organizations')->drop();
        $this->table('registry_access_tokens')->drop();
        $this->table('user_oauth_accounts')->drop();
        $this->table('users')->drop();
        $this->table('password_reset_tokens')->drop();
        $this->table('email_verification_codes')->drop();
    }
}
