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

To run the same checks locally, initialize the build-system submodule and
install the tools:

```sh
git -c url."https://github.com/".insteadOf="git@github.com:" submodule update --init dev-scripts/lib
composer install --working-dir=vendor-bin/phpcs --no-interaction --no-progress
composer install --working-dir=vendor-bin/phpmd --no-interaction --no-progress
vendor-bin/phpcs/vendor/bin/phpcs --standard=.phpcs.xml --extensions=php --runtime-set ignore_warnings_on_exit 1 --ignore='lib/Service/IMAP/*,lib/Legacy/PhpMyEdit/*,lib/Database/Doctrine/ORM/Proxies/*' lib/
vendor-bin/phpmd/vendor/bin/phpmd lib/ --format text --ruleset .phpmd.xml --suffixes php --exclude '*/lib/Service/IMAP/*' --exclude '*/lib/Legacy/PhpMyEdit/*' --exclude '*/lib/Database/Doctrine/ORM/Proxies/*' --threads 1 --no-cache
```

PHPMD runs with one thread because its patched entry point cannot dispatch
worker subcommands.

--
cH
