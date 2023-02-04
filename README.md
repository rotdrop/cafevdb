CAFEVDB -- Orchestra Management App
===================================

# BRANCH COMMENTS

PHP native enums are ill-disigned and not yet feature-full enough. It
is not even possible to use the enum values as constants
conveniently. Therefore this branch is abandoned until -- perhaps --
PHP 9.

Until then my judgement is: PHP native enums are not yet mature and
not yet in a state that they are ready for production use.

This branch is freezed until this changes. If it ever changes ...

# Rest

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

--
cH
