# PHP runtime dependencies

Packio's Composer lock file defines both the production dependency set and its
PHP platform requirements. The production image installs it with
`composer install --no-dev`; development and test tools are not part of the
runtime image. `ext-sodium` is a required runtime extension and must remain
declared in Composer metadata so Composer can reject an incompatible runtime.

The release regression fixture exercises those guarantees with a fresh
production install. Build the existing RoadRunner runtime target, then run the
fixture:

```sh
docker build -f .docker/Dockerfile.roadrunner --target app-runtime \
  -t packio-s4-runtime-20261009:local .
python3 test/Support/Release/dependencies.test.py
```

Use `--runtime-image IMAGE` or `PACKIO_S4_RUNTIME_IMAGE` to select another
already-built runtime image. The fixture makes a disposable context from the
tracked working tree while excluding local environment files, ignored files,
generated data, and local plans. It installs only production dependencies
inside a derived image, verifies Composer platform
requirements and the absence of development packages, then confirms that a
derived runtime with sodium disabled fails specifically on `ext-sodium`.
Finally, it applies actual migrations to a fresh private PostgreSQL database
before listing all Laminas commands and warming the object mapper and
routing-attributes cache. This matches production startup; listing every CLI
service before migrations is not a supported empty-database boot check.

The test uses Python's standard library and Docker. PostgreSQL data lives in a
temporary container filesystem; the fixture publishes no ports, uses no host
bind mounts, and removes only its own containers, network, and derived images.
Composer must be able to download locked production packages while building,
and Docker must have the PostgreSQL image available. The application checks
run on an internal-only network with synthetic credentials.

`composer-dependency-analyser.php` keeps only package-specific exceptions for
contracts the static scan cannot see: three Cycle migration classes loaded by
the migration runner, optional Mezzio providers guarded by `class_exists()`,
the schema migration CLI, Laminas CLI and its installer plugin, the lazy
service proxy, and Laminas CLI's use of `laminas/laminas-stdlib`. Its extension
rules retain the production PDO/PostgreSQL requirement and the existing DOM,
iconv, OpenSSL, SimpleXML, and XMLWriter runtime contract. These are narrow
analyser rules; they do not remove the runtime requirements from Composer.

The manifest explicitly declares 17 runtime requirements for PHP and its
extensions; these do not add package nodes or versions to the locked package
graph. Direct test-only imports of `league/event`, `sentry/sentry`, and
`symfony/event-dispatcher` are explicitly declared in `require-dev`. Existing
production transitive consumers keep those package entries in the `--no-dev`
installed graph. No package versions or graph packages were added, and there
are no blanket analyser suppressions.

This command validates the current working tree. It does not establish that a
clean release candidate passed the gate; run it again against the exact source
tree prepared for release.
