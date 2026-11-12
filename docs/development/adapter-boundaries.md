# Adapter boundaries

Worker payload parsing and `JobResult` mapping belong to `Infrastructure/Adapter/Worker`.
Source, Package, Registry and Identity handlers follow the Organization handler convention;
Release currently has no job handler implementation. Source execution services retain
application orchestration and accept application inputs and PSR logging contracts.
`SourceJobLogger` adapts PSR logging to the Worker job log inside Infrastructure.

Provider webhook implementations, their registry/configuration and the HTTP envelope
belong to `Webhook/Infrastructure/Webhook`. `WebhookDispatcher` selects a provider
through `WebhookProviderHandlerInterface`, interprets headers, verifies the signature
and drives the delivery lifecycle through the application `WebhookCommandService`
(`findDelivery()`, `receiveDelivery()`, `handleVerifiedEvent()`). `handleVerifiedEvent()`
receives the stored event and a typed, normalized `WebhookInspectionResult` from
`Application/Command`; neither raw JSON nor headers enter the application layer.
The normalized payload expresses processed/ignored/rejected and the existing message.
The Webhook `Application/Port` holds only `SourcePort`.

The provider signature is validated before delivery deduplication. A duplicate
valid delivery returns its existing event. New deliveries are recorded before
payload inspection and handling; inactive sources are recorded and returned without
inspection. Signature failures remain rejected requests. Provider algorithms,
missing-secret policy, response mapping, sync scheduling and reprocessing semantics
are unchanged.

## HTTP repository inventory

The inventory of `src/Module/*/src/Infrastructure/Handler` has no remaining direct
domain repository dependencies and no exceptions. A broader scan of all 179 HTTP
`RequestHandlerInterface` implementations confirms the same result.

- Identity `RegistryTokensHandler` and Rest `RegistryTokensIndexHandler` use
  `IdentityApiFacadeInterface::registryAccessTokensForOrganization()`. Identity owns
  organization-scoped lookup, secret decoding and result normalization.
- `RegistryTokenRevokeHandler` validates the route UUID and uses the existing
  actor-scoped revoke command. Organization and token ownership checks remain in
  Identity; the handler no longer loads and decrypts the entire token list.
- `OrganizationMembersHandler` already uses `OrganizationMembersViewQuery` from
  plan 08 and needs no additional facade.

Deptrac permits Infrastructure → Application and rejects Application → Infrastructure.
`AdapterBoundaryTest` additionally prevents Worker handler implementations in
Application, webhook transport parsing in Application and repositories in HTTP handlers.
