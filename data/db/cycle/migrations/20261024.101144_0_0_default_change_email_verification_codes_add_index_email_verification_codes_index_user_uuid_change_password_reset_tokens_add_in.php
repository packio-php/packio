<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class OrmDefault76150c6df55d9abd2aa66fd2811b15fd extends Migration
{
    protected const DATABASE = 'default';

    public function up(): void
    {
        $this->table('email_verification_codes')
            ->addIndex(['user_uuid'], [
                'name'   => 'email_verification_codes_index_user_uuid',
                'unique' => false,
            ])
            ->update()
        ;
        $this->table('password_reset_tokens')
            ->addIndex(['selector'], [
                'name'   => 'password_reset_tokens_unique_selector',
                'unique' => true,
            ])
            ->update()
        ;
        $this->table('registry_access_tokens')
            ->addIndex(['token_hash'], [
                'name'   => 'registry_access_tokens_unique_token_hash',
                'unique' => true,
            ])
            ->addIndex(['organization_id'], [
                'name'   => 'registry_access_tokens_index_organization_id',
                'unique' => false,
            ])
            ->update()
        ;
        $this->table('users')
            ->addIndex(['email'], [
                'name'   => 'users_email_unique',
                'unique' => true,
            ])
            ->update()
        ;
        $this->table('user_oauth_accounts')
            ->addIndex(['provider', 'provider_user_id'], [
                'name'   => 'user_oauth_accounts_unique_provider_user',
                'unique' => true,
            ])
            ->update()
        ;
    }

    public function down(): void
    {
        $this->table('user_oauth_accounts')
            ->dropIndex(['provider', 'provider_user_id'])
            ->update()
        ;
        $this->table('users')
            ->dropIndex(['email'])
            ->update()
        ;
        $this->table('registry_access_tokens')
            ->dropIndex(['token_hash'])
            ->dropIndex(['organization_id'])
            ->update()
        ;
        $this->table('password_reset_tokens')
            ->dropIndex(['selector'])
            ->update()
        ;
        $this->table('email_verification_codes')
            ->dropIndex(['user_uuid'])
            ->update()
        ;
    }
}
