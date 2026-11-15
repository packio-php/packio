# PHP runtime dependencies

Packio's Composer lock file defines both the production dependency set and its
PHP platform requirements. The production image installs it with
`composer install --no-dev`; development and test tools are not part of the
runtime image. `ext-sodium` is a required runtime extension and must remain
declared in Composer metadata so Composer can reject an incompatible runtime.

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
