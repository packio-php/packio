<?php

declare(strict_types=1);

namespace Packio\Shared\Api\Dto;

final readonly class OrganizationDto
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public bool $anonymousAccess = false,
        public bool $active = true,
        public string $status = 'active',
        public string $createdAt = '',
        public string $updatedAt = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'name'            => $this->name,
            'anonymousAccess' => $this->anonymousAccess,
            'active'          => $this->active,
            'status'          => $this->status,
            'createdAt'       => $this->createdAt,
            'updatedAt'       => $this->updatedAt,
        ];
    }
}
