## Git workflow

- Never commit or push task changes directly to the repository's default branch, regardless of its name.
- Before committing, determine the default branch and create a topic branch if currently on it.
- Open pull requests from the topic branch against the original base branch.

## Shared git subrepos

- Before changing code inside a git subrepo, read its `.gitrepo` to identify the boundary and upstream. Treat its contents as reusable upstream code, not app-local code; never edit `.gitrepo` manually.
- `tests/phpunit/lib` is the shared `rotdrop/nextcloud-app-phpunit` subrepo used by CafeVDB and CafeVDB Members. Keep its reusable helpers consumer-neutral: do not hard-code either `OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit` or `OCA\CAFeVDBMembers\Wrapped\OCA\RotDrop\Toolkit` in shared helpers, DTO superclasses, traits, or constructor types. App-specific scoped namespaces are valid in app-local wiring outside the subrepo. Explicit namespace strings used as compatibility-test inputs or in documentation are not runtime coupling.
- Follow the current shared subrepo README for app-neutral integration. Select the toolkit using `PHPUNIT_NC_APP_NAMESPACE`, preserving DTO inheritance, logger behavior, and concrete finder typing; make the neutral finder resolve to the consuming app's existing scoped service, not a second instance. Before resolving the provider, initialize Nextcloud, load Composer and scoped autoloaders, define the app constants, and register app services by loading the app.
- Before changing shared behavior, inspect the current default branches of both consumers rather than assuming branch names. Verify CafeVDB independently without Members installed and Members' app-specific `DatabaseProvider` subclass. Review the upstream-export diff for consumer-specific namespaces or dependencies; keep app-only adapters/configuration outside the subrepo and state any required consumer integration and validation limitations explicitly.
