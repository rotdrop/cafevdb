CAFEVDB -- Orchestra Management App
===================================

# Branch feature/mailing-lists #

## Purpose ##

Provide global and per-project mailing lists for the communication to
the registrered musians and between the registered musicians.

## Comments ##

- data-security issues?

- moving all the data-base addresses to the tentative "global" mailing-list is also problematic concering the consent of the participants. Still better than posting again @all

# General README #

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
