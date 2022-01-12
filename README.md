CAFEVDB -- Orchestra Management App
===================================

# Branch feature/member-access #

## Purpose ##

Give orchestra-members access to selected parts of the data-base,
i.e. let them view their own (and only their own) data. Ideally

- enable update of personal address data
- download access to bills, insurance records etc.

## Comments ##

It would be possible to implement row-level access restriction in
mysql using views and the pam system, see

https://mariadb.com/kb/en/authentication-with-pluggable-authentication-modules-pam/

Not-so-easy.

Advantages:
- authentication with the data-base just requires the user-password
  - no shared password for the proxy/readonly user would have to be distributed
  - password-change of the user would just work
- data would already be filtered out at the data-base level

Disadvantages:
- needs manipulation of the system-auth services on the server
  - needs group-membership in a dummy group via libnss-mysql
  - however, the generated users would have no privileges in the system

Alternative:
- generate a proxy/readonly user with a known password
- distribute that password somehow to the users (perhaps encrypted
  using a public/private key-pair)
- filter-out the allowed data on the PHP-level by only placing
  restricted queries.

In both cases one might want to move all of the member-access stuff to
a separate app, although this would duplicate code.

Samples for the setup of the PAM-based scenario are in `appinfo/database/auth/`.

On the server-side views could be generated depending on the value of
`USER()`, while `CURRENT_USER()` would be the single proxy-user which
actually has access to the data-base
```
MariaDB [cafevdb_musicians_insurances]> SELECT USER(), CURRENT_USER();
+-------------------------+--------------------------------+
| USER()                  | CURRENT_USER()                 |
+-------------------------+--------------------------------+
| bilbo.baggins@localhost | cafevdb_member_proxy@localhost |
+-------------------------+--------------------------------+
1 row in set (0.011 sec)
```
Need additional proxy and catch-all user in the DB-server like the following, where `mariadb-cafevdb` is the name of the PAM-service
``` sql
CREATE USER 'cafevdb_member_proxy'@'localhost' IDENTIFIED BY 'strongpassword';
GRANT SELECT ON cafevdb_musicians_insurances.* TO 'cafevdb_member_proxy'@'localhost';

CREATE USER ''@'localhost' IDENTIFIED VIA pam USING 'mariadb-cafevdb';
GRANT PROXY ON 'cafevdb_member_proxy'@'localhost' TO ''@'localhost';
```
Of course, the grants for the proxy-user
`cafevdb_member_proxy@localhost` should be as restrictive as possible.

Row-level access-restriction example:

https://mariadb.com/resources/blog/protect-your-data-row-level-security-in-mariadb-10-0/

An simple example which would just return the single row from the
Musicians-table referring the member would be
``` sql
CREATE
SQL SECURITY DEFINER
VIEW cafevdb_musicians_insurances.MusiciansForMembers
AS
    SELECT *
    FROM cafevdb_musicians_insurances.Musicians m
    WHERE CONCAT(`m`.`user_id_slug`,'@localhost') = USER()
WITH CHECK OPTION;
```
Of course, one could formulate this in a more abstract way. The
access-restricted views could also be moved to a separate database,
s.t. the grants for the proxy-user could be formulated more easily.

# General README #

## Purpose ##

Provide a multi-user encryption for certain database-entries. Idea
would be to make personal data available for club-members for review
or even editing.

## Comments ##

This could work like the openssl_seal() function. As usual there are
questions how to organize re-encryption on password change and the like.

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
