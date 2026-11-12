# Mapped request validation

Declare `#[MapRequest(source: RequestDto::class)]` and
`#[ValidateMappedRequest(RequestDto::class)]` on the route. DTOs implement
`RulesProviderInterface`; normalized accessor-based DTOs also implement
`DataSetInterface` (see `CreateOrganizationRequest`). The middleware rejects
invalid input before the handler is called. Keep business validation and policy
checks in application services and middleware.

- Required strings: normalize with `ApiRequestPayload::string`, then `Required`
  with the existing public message. Do not read raw input in handlers.
- Enums: use `In` with strict comparison and an explicit message.
- Objects/arrays: validate the normalized array with a callback when empty arrays
  are valid; `Required` would reject them. Preserve existing list normalization
  and defaults unless changing the API contract deliberately.
- PATCH: distinguish absence from a supplied invalid value. Expose only present
  fields through `DataSetInterface` and use `WhenMissing`, or use
  `ApiRequestPayload::has` before selecting rules for optional fields. Resolve
  actual existing-resource defaults
  in the handler after validation. Never overwrite omitted fields with DTO defaults.
- Keep canonical snake_case error keys and legacy aliases. `SourceSettingsInput`
  demonstrates partial PATCH, boolean/enum/integer validation and alias precedence.
- Preserve existing error grouping where clients depend on it. Source creation
  validates the required pair first, then visibility, retention and settings;
  its API response adapter retains empty arrays for the valid member of that pair.
- Nullable normalized accessors are guaranteed valid after middleware; narrow
  their types at the consumption point. Do not duplicate validation responses.
- Contract tests must cover the real mapping/validation pipeline, exact error
  envelope, valid defaults, invalid types and absence of downstream mutations.

Migration scope and exceptions:

- API organization/member/invitation, provider create/update, admin update and
  token create DTOs use the shared middleware. Token regeneration has no mapped
  body and uses the route resolver.
- Provider delete/reassign DTOs remain mapper-only: replacement authorization is
  optional and application services decide eligibility from resource state.
- Release activity also validates its mapped query `version` and body `active`,
  preserving version-first errors and the existing default for omitted `active`.
- Confirmation-name checks on organization/source deletion compare against the
  resolved resource and remain business checks. Public domain exceptions remain
  handler response mappings, not repeated DTO validation.
- Provider resource and release show query-only endpoints have no mapped DTO;
  their query validation is outside this migration.
- `ApiRouteResourceResolver` normalizes IDs and performs source/authorization
  lookups scoped to the current organization. Its organization lookup is for
  admin resources. Authorization remains in policies/context-aware facades,
  including existing forbidden responses for provider-instance ownership.

Families were implemented and verified sequentially in the workspace. Publishing
or deploying individual releases is a separate operational step.
