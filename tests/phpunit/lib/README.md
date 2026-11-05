# nextcloud-app-phpunit

Just some utitlity classes shared between some of my nextcloud apps, in order to avoid code duplication. This is not meant as standalone-package. Currently it is used by [rotdrop/cafevdb](https://github.com/rotdrop/cafevdb) and [rotdrop/cafevdb-members](https://github.com/rotdrop/cafevdb-members).

## PHPUnit bootstrap integration

Before loading `DatabaseProvider` (including app-specific subclasses) or
`DatabaseConfig`, the consuming app must:

1. Initialize Nextcloud, including its server container.
2. Load its Composer and scoped (`vendor-wrapped`) autoloaders.
3. Define `PHPUNIT_NC_APP_NAMESPACE` as its app namespace, without a trailing
   backslash, and `PHPUNIT_NC_APP_NAME` as its app ID.
4. Load the app so its services are registered before resolving the provider.

Both helpers automatically load `bootstrap.php`. Alternatively, the app's
`tests/phpunit/bootstrap.php` can explicitly load it after the steps above and
before resolving the provider:

```php
require_once __DIR__ . '/lib/bootstrap.php';
```

The shared bootstrap aliases the active app's
`PHPUNIT_NC_APP_NAMESPACE . '\\Wrapped\\OCA\\RotDrop\\Toolkit'` DTO base class,
logger trait, and executable finder to `OCA\RotDrop\Tests\Toolkit`. It also
registers a Nextcloud container alias for the finder, preserving the active
app's existing service instance and constructor type safety.

The existing bootstraps on CafeVDB's `nextcloud35` and Members' `stable35`
branches already meet these prerequisites; pulling the updated subrepo does
not require bootstrap edits. Each app must run in its own PHP process, since
the app namespace constant and toolkit aliases are process-wide. The other
app does not need to be installed.

## Regression checks

Run each app and helper load order in a separate process:

```sh
for app in 'OCA\CAFEVDB' 'OCA\CAFeVDBMembers'; do
  php tests/verify-bootstrap.php "$app" DatabaseProvider
  php tests/verify-bootstrap.php "$app" DatabaseConfig
done
php tests/verify-bootstrap.php missing-namespace
php tests/verify-bootstrap.php missing-toolkit
```

These dependency-free checks use isolated toolkit and container fixtures. They
cover alias selection, DTO inheritance and serialization, logger access, finder
type safety and service identity, repeated bootstrap loading, and invalid setup
diagnostics. They do not start a database or replace the consuming apps' PHPUnit
suites.
