# PHPUnit test-suite

Two modes, selected in `bootstrap.php`:

- **Nextcloud mode** (default if the app lives in a Nextcloud server tree):
  uses the server's `tests/bootstrap.php` and loads the apps. Runs everything.
- **Standalone mode** (`PHPUNIT_STANDALONE=1`, or no server tree found): uses
  only the composer dependencies, `OCP\*` comes from the `nextcloud/ocp`
  stubs in `vendor-bin/phpunit`. Run with `make phpunit-standalone`, which
  excludes tests tagged `#[Attributes\Group('nextcloud')]`.

Tests that need real server classes (`OC\*`, `OC_Calendar`), the test
database (`DatabaseProvider`) or `MockProvider` are tagged with the
`nextcloud` group. Untag a test once it no longer needs the server.
