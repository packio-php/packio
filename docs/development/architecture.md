# Module architecture

Every directory in `src/Module` (except `Shared`) is a module with the namespace `Packio\<Name>`.
Its code is split into parts, and each part may depend only on the parts listed below.
`composer deptrac` enforces this; the layer definitions are in `deptrac.php`.

## Module parts

| Part (deptrac layer) | Namespace                                                                | May use                                                              |
| -------------------- | ------------------------------------------------------------------------ | -------------------------------------------------------------------- |
| `Api`                | `<M>\Api`                                                                | own Api, Shared, Common, vendor                                      |
| `Domain`             | `<M>\Domain`                                                             | the above + own Api                                                  |
| `Application`        | `<M>\Application`                                                        | the above + own Domain                                               |
| `Adapter`            | `<M>\Infrastructure\Adapter`                                             | own parts, Platform, Shared, **foreign Api**                         |
| `Facade`             | `<M>\Infrastructure\Facade`                                              | own parts, Adapter, Platform, Shared, **no foreign Api**             |
| `Entry`              | rest of `<M>\Infrastructure` and the module root (`ConfigProvider`, ...) | own parts, Platform, Shared, foreign Api                             |

"Own parts" are the module's Api, Domain, Application and Entry. Entry may also use its own Adapter and Facade, Facade
may also use its own Adapter, and Adapter may not use its own Facade. Every part may use Common and vendor code.

Outside the modules: `Common` (`Common\`) uses only vendor code. `Shared` (`Packio\Shared`) has `Api`, which may use
`Domain`. `Platform` (`Packio\Application`, the HTTP composition root) uses Shared and the `Api` of every module.
DI configs in `config/` are the composition root and are not checked.

## Calling another module

- Nobody imports another module's `Domain`, `Application` or `Infrastructure`. Entry code (handlers, middleware, CLI)
  may call another module's `Api` directly.
- Facades and the core (`Domain`, `Application`) do not use a foreign `Api`. When they need another module, the
  consumer declares `Application/Port/<What>Port`, and `Infrastructure/Adapter/<Supplier><What>Adapter` implements it
  by calling the supplier's `Api` and mapping its DTOs to the consumer's types.
- `Port` and `Application/Port` are reserved for this module boundary. Other abstractions are not called ports:
  repositories are `Domain/Repository/*RepositoryInterface`, the rest are `<What>Interface` next to the code that uses them.
- Worker job handlers are adapters to the Worker contract and live in `Infrastructure/Adapter/Worker`.

## What goes into `Api`

Allowed: facade interfaces, DTOs, enums, events, public exceptions and policies without dependencies.
Not allowed: services with dependencies (they live in `Application` and are exposed through a facade) and references
to another module's `Api`.

## Shared types

`Packio\Shared\Api` and `Packio\Shared\Domain` hold a closed list of types used by many modules: `UserDto`,
`OrganizationDto`, `OrganizationMemberDto`, `PaginatedResultDto`, `CurrentUserActorInterface`, `ActorContext` and
`PaginationCriteria` and `SortDirection`. Adding a type needs a maintainer decision.
Deptrac reserves the `SharedInfra` layer for other `Packio\Shared\*` code (outside `Api` and `Domain`), usable by
Platform and module Adapter, Facade and Entry. No such code exists today.

## Background jobs

The Worker contract is in `Worker\Api`: `Job\JobHandlerInterface`, `Job\JobLoggerInterface`, `Job\JobResult` and
`Exception\WorkerRestartRequiredException`. Other modules enqueue jobs through `JobApiFacadeInterface`.

## Checks

- `composer deptrac` fails on forbidden dependencies and on dependencies outside any layer. A new `src/Module/<Name>`
  directory is picked up automatically; its namespace must be `Packio\<Name>`.
- `test/Unit/Architecture` checks that ports live in `Application/Port` and are implemented in
  `Infrastructure/Adapter`, that Worker job handlers are not in `Application`, that webhook `Application` does not parse
  transport, that HTTP handlers do not use domain repositories, and that handlers do not classify exceptions by message.

Adapter and webhook conventions are in [Adapter boundaries](adapter-boundaries.md).
