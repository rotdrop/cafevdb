CAFEVDB -- Orchestra Management App
===================================

# Branch feature/project-registration-form #

## Purpose ##

Provide a per-project registration form with the following features:

- tentative registration to a project by email-address or name, with auto-fill for known persons
  - security? At least a captcha is necessary, or an email registration round-trip
- check-marks for
  - acceptance of required contact/personal data storage
    - email/phone/address contact for project
    - per-project mailing list subscription for project
  - optional contact "features"
    - global news-letter registration

On form submit:
- the registration should then be filled into the respective project table
  - this is not an auto-registration, the person applies for acceptance
  - of course with a "confirmation required" note
- the person should get email-feedback
- the orga-team should be triggered by email and/or push-message

After acceptance by the orga-team:
- automatic adding to the per-project communication channels
  - mailing list?
  - chat channel?
  - SMS notifications?

## Comments ##

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
is based on Doctrine/ORM. This is also the reason why the App
currently does not work with Nextcloud later than v20, as Doctrine/ORM
still does not support Doctrine/DBAL 3.0, but Nextcloud >= 21 depends
on DBAL v3.

--
cH
