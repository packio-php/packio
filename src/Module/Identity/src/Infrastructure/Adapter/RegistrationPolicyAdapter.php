<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Adapter;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Identity\Application\Port\RegistrationPolicyPort;
use Packio\Organization\Api\Dto\OrganizationInvitationDto;
use Packio\Organization\Api\OrganizationApiFacadeInterface;

use function preg_match;
use function rawurldecode;

final readonly class RegistrationPolicyAdapter implements RegistrationPolicyPort
{
    public function __construct(
        private ProjectSettingsApiFacadeInterface $projectSettingsApiFacade,
        private OrganizationApiFacadeInterface $organizationApiFacade
    ) {}

    public function allowsRegistration(string $redirectTo): bool
    {
        if ($this->projectSettingsApiFacade->registrationOpen()) {
            return true;
        }

        return $this->hasPendingInvitationRedirect($redirectTo);
    }

    private function hasPendingInvitationRedirect(string $redirectTo): bool
    {
        $matches = [];
        if (1 !== preg_match('~^/organization/invite/([^/?#]+)$~', $redirectTo, $matches)) {
            return false;
        }

        $invitation = $this->organizationApiFacade->findInvitationByToken(rawurldecode($matches[1]));

        return $invitation instanceof OrganizationInvitationDto && 'pending' === $invitation->status;
    }
}
