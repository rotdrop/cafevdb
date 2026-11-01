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
git -c url."https://github.com/".insteadOf="git@github.com:" submodule update --init dev-scripts/lib dev-scripts/MakeHelp 3rdparty/selectize
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
cH
