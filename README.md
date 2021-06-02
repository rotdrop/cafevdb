BE WARNED: THIS BEAST -- unfortunately -- IS NOT YET IN A SHAPE THAT
IT COULD BE INSTALLED EASILY BY MERE MORTALS.

So what is this about? The goal is a group-ware application which aids
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
- export of bulk-transactions in from suitable for input to AqBanking-CLI

The orchestra data-base is held separate from the cloud database and is based on
Doctrine/ORM. This is also the reason why the App currently does not work with
Nextcloud later than v20, as Doctrine/ORM still does not support Doctrine/DBAL 3.0,
but Nextcloud >= 21 depends on DBAL v3.

--
cH
