## Git workflow

- Never commit or push task changes directly to the repository's default branch, regardless of its name.
- Before committing, determine the default branch and create a topic branch if currently on it.
- Open pull requests from the topic branch against the original base branch.

## Shared PHPUnit helpers

- Before changing code inside a git subrepo, read its `.gitrepo` to identify the boundary and upstream. Treat its contents as reusable upstream code, not app-local code; never edit `.gitrepo` manually.
- `OCA\RotDrop\Tests\` is provided by the `rotdrop/nextcloud-app-phpunit` Composer development dependency in `vendor-bin/phpunit`, not by app-local source or the namespace-scoped production dependencies. Follow that package's README for dependency setup and helper integration.
- Keep shared helpers consumer-neutral: do not hard-code either `OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit` or `OCA\CAFeVDBMembers\Wrapped\OCA\RotDrop\Toolkit` in DTO superclasses, traits, or constructor types. Select the toolkit using `PHPUNIT_NC_APP_NAMESPACE`, preserve DTO inheritance and logger behavior, and make the neutral finder resolve to the consuming app's existing scoped service instance while retaining concrete finder typing.
- Before resolving the shared provider, initialize Nextcloud, load the PHPUnit bin-project, app and scoped autoloaders, define the app constants, and load the app so its services are registered.
