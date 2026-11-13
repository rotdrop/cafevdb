CAFEVDB -- Orchestra Management App
===================================

The goal of the project is a group-ware application which aids
the management of a project-oriented orchestra. This stuff arose out
of the frustation about collections of unwieldy "Excel"-sheets and
other frustrations. The web-site of the concrete orchestra in question
is

http://www.cafev.de/

The current state of the affair is wrapped into a Nextcloud appliction.

Features include

- address management
- project management
  - selection of project participants
  - instrumentation numbers
  - custom per-participant per-project data like service fees, twin-rooms etc.
  - form letters with substitutions
- export of participation lists and other tables in office spread-sheet formats
- integration of a DokuWiki
- integration of web-pages, currently only the old ancient Redaxo4
- addressbook integration via Nextcloud
- encryption of sensible information
- management of bank accounts and debit mandates
- export of bulk-transactions in form suitable for input to AqBanking-CLI

The orchestra data-base is held separate from the cloud database and
is based on Doctrine/ORM.

Frontend builds
---------------

`make build` generates PHP-derived TypeScript types and entity metadata before
building the production frontend assets. It requires an installed Nextcloud
instance and its own database (SQLite is sufficient), but not the orchestra
MySQL/MariaDB database, a CafeVDB user, or `.clipassword`.

The app-local converter uses `11.7.0-MariaDB` as a representative DBAL platform
without opening the application connection. Encryption operations are disabled
and bound-event application subscribers are isolated in this metadata-only
process. Normal requests and the ORM CLI retain encryption, connection probing,
server-version detection and connection initialization.

`EntityRepositoryControllerTest` and the Vitest entity-response generators
likewise use an explicit MariaDB platform for serialization metadata. Their
repositories and entities are in-memory fixtures; they do not start an orchestra
database server and check that serialization leaves the connection unopened.

Office document credentials
---------------------------

A global response middleware provisions database-mount credentials for successful
richdocuments `DocumentController::token()`, `index()`, and
`createFromTemplate()` responses. It uses the returned token to load the
authoritative WOPI record, checks filesystem authorization and matching local
user identities, and only stores credentials for mounts marked `authenticated`.
Other responses and ordinary mounts are left unchanged.

This temporarily retains the fork's recoverable-password authentication-token
format for compatibility with WOPI mount authentication: the lookup passphrase
is `USER_ID@WOPI_TOKEN`, the stored token UID is the WOPI token, and the login
name is the local user ID. Expiry follows the WOPI record. Provisioning failures
return an error without exposing credentials or tokens in the response or log.
It requires password credentials in the current Nextcloud login session.

This covers only those three document-opening endpoints, not every richdocuments
token producer. The mount-side authentication integration is required separately;
this middleware does not log in WOPI requests. Removal of the fork's credential
provisioning is performed separately.

`CleanupWopiCredentials` runs every ten minutes and invalidates up to 1,000
expired temporary credentials per run, allowing a 60-second expiry grace period.
It requires a positive expiry and the exact `wopi_token_` plus token-UID name,
and rechecks each candidate before invalidating it through Nextcloud's token
provider. Ordinary app passwords and credentials without an expiry are excluded.
It also handles credentials created by the fork, even after their WOPI records
have disappeared or richdocuments has been disabled. It does not modify
richdocuments tables, access the orchestra database, or require a user session.
Deploy CafeVDB with this job registered before removing the fork's custom cleanup;
richdocuments' standard WOPI and template cleanup should remain enabled.

User database recryption
------------------------

Run `occ cafevdb:users:recrypt --user=USER` or
`occ cafevdb:users:recrypt --all` to force recryption, regardless of pending
recryption requests. The command prompts for the credentials of an account
that can decrypt the app database; `--user` selects the target, not that account.
`--all` selects all Nextcloud users, including those without a musician record.
For each linked musician this renews the row access token and recrypts their
bank accounts and encrypted file data, including soft-deleted entries. Users
with app permissions also receive the app encryption key.

Add `--dry-run` to enumerate without changing tokens, keys or encrypted entries.
Add `-v` or `--verbose` to list each encrypted entity's class and identifier as
it is processed (or would be processed in a dry run); decrypted contents are
not printed. Failures stop the command with a non-zero exit status.

Testing problem reports
-----------------------

In the admin settings, open **Configure Problem Report Handling** and click
**Generate Test Error**. Use **report error** in the resulting dialog to preview
and submit the artificial error through the configured reporting methods.
Generating the error alone sends no report. Submission uses the real reporting
destinations, including a public GitHub issue when configured.

App-local word splitter
-----------------------

`make build`, `make dev`, and `make appstore` build a Shiv zipapp containing the
Python splitter and its runtime dependencies, and download its German dictionary.
They do not require or copy the Python submodule's development virtualenv.
The app-store archive includes only `python/german-word-splitter.pyz` and
`python/german_utf8_linux.dic`, not the splitter's source tree, tests, or build tools.

Build prerequisites are Python 3.13 with `venv`/`pip`, `git`, `curl`, `7z`, and
`iconv`. Initialize `python/compound-word-splitting` first. The splitter can also
be built without Nextcloud:

```sh
make -f dev-scripts/word-splitter.mk word-splitter
```

`SPLITTER_PYTHON` selects the build interpreter (default: `python3.13`). The
destination server must have that same Python major/minor on the web server's
`PATH`, compatible native libraries, and the same OS/CPU architecture as the
build machine: dependencies include native extensions, so this is not a
universal binary. Python itself is not bundled. Build release archives on the
oldest supported Linux runtime, and build separately for other architectures.
No pip install or network access is needed on the destination server.

The app passes the installed dictionary path explicitly and extracts the zipapp
into a writable cache under its Nextcloud app-data directory; that filesystem
must permit loading native libraries. Required locales and `iconv` must be
installed on the server. A usable system-wide splitter is still preferred over
the bundled service.

To check the packaged executable, dictionary, and both splitting backends after
relocation, run `python3.13 tests/scripts/word-splitter-smoke.py <appstore.tar.gz>`.
CI runs this against the unsigned app-store archive.

Outgoing email form
-------------------

The outgoing email form uses a Vue envelope filling the legacy content area,
mounted through the component event bus. Vue owns the tabs and common form
structure; the initial form endpoint supplies the existing PHP-rendered
recipients and composition fragments, hidden form data and upload/manual
metadata instead of a complete
outer template. All panels remain mounted inside one form when switching tabs.
Minimize leaves the form and its auto-save timer alive; restore returns to the
same message. The editor, attachments and status/preview rendering remain
legacy-owned. The Vue component never imports the legacy JS asset: typed
`EMAIL_FORM_COMMAND` and `EMAIL_FORM_EVENT` bus messages coordinate tab changes,
activation hooks, title, blocking, close and lifecycle cleanup.
Closing from the preview returns to composition; closing from composition uses
the existing cancel and draft-cleanup flow. Sending, loading a message and
uploading attachments block the envelope controls until the operation completes.
Removing the containing legacy view also tears down the envelope and its
legacy widgets, after any protected operation finishes.
The panels fill the available envelope width without the old dialog width caps.
The envelope and restore button are absolutely positioned within the existing
legacy content container. Its flex layout determines their available space,
including sidebar changes, without navigation observers or manual offsets.

Email template substitutions
----------------------------

Email templates support substitutions in both the subject and the body,
for example `${GLOBAL::PROJECT}` and `${MEMBER::FIRST_NAME}`. Per-member
substitutions in either field generate a separate email for each recipient
and cannot be used for mailing-list posts. The Cc/Bcc and catch-all copy
resolves global substitutions but keeps the per-member placeholders.
Use `$$` to escape a literal dollar sign. Stored templates and drafts retain
their placeholders.

German bank validation data is stored through Doctrine/ORM in the Nextcloud
database, not in the orchestra database or a vendor bank-list file. It uses
the same unprefixed `bav_bank`, `bav_agency`, and `bav_meta` tables as the
optional BAV Nextcloud app, so existing bank data is shared even though each
app has its own namespace-wrapped library. The BAV app is not required.
Missing bank tables are installed and populated automatically; downloads
for installation and explicit updates use temporary files.

PHP code quality
----------------

The PHPCS and PHPMD workflows check PHP files in `lib/` using `.phpcs.xml`
and `.phpmd.xml`. They omit `templates/`, `lib/Service/IMAP/`, legacy PhpMyEdit
in `lib/Legacy/PhpMyEdit/`, and generated Doctrine ORM proxies in
`lib/Database/Doctrine/ORM/Proxies/`.
PHPCS reports warnings but only fails CI on errors.

To run the same checks locally, initialize the Makefile submodules and ensure
the `xpath` binary is installed (`libxml-xpath-perl` on Debian/Ubuntu). The
targets install their isolated Composer tools when needed:

```sh
git -c url."https://github.com/".insteadOf="git@github.com:" submodule update --init dev-scripts/lib dev-scripts/MakeHelp
make phpcs
make phpmd
```

PHPMD runs with one thread because its patched entry point cannot dispatch
worker subcommands.

The app's Doctrine dependencies are namespace-wrapped with PHP-Scoper. Its
configuration rewrites dynamic class-constant lookups in Doctrine's attribute
driver and DQL lexer, and CI verifies attribute metadata, SQLite schema
creation, and DQL execution against the wrapped ORM.

--
cjh
