<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace OCA\CAFEVDB\Tests\Unit\Service;

use OCP\IL10N;

/**
 * Some database rows in order to have some data to for testing. This is from the oc_calendarobjects table.
 * @phpcs:disable
 * @phpcs:disable Internal.LineEndings.Mixed
 * @phpcs:ignore Internal.LineEndings.Mixed
 */
class CalendarObjects
{
  private const DATA = [
    [
      'id' => '641',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//IDN georgehrke.com//calendar-js//EN
CALSCALE:GREGORIAN
BEGIN:VTIMEZONE
TZID:Arctic/Longyearbyen
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:20250421T192458Z
SEQUENCE:2
DTEND;TZID=Arctic/Longyearbyen:20250427T110000
STATUS:CONFIRMED
DTSTART;TZID=Arctic/Longyearbyen:20250427T000000
UID:6ad26e58-6c3a-48bf-9d0b-0579dfee252e
RELATED-TO;RELTYPE=SIBLING:1bb239f6-6110-4d3f-a84b-ab0c9bfcab64
RELATED-TO;RELTYPE=SIBLING:1ceae044-424c-4984-b851-1c1014262fa3
DTSTAMP:20250421T192519Z
LAST-MODIFIED:20250421T192519Z
CATEGORIES:%PROJECT_NAME%,other,Sonstiges
SUMMARY:Sonstiges\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => '5CE8528A-1EE6-11F0-8CB2-C77D3281A19A.ics',
      'calendarid' => '47',
      'lastmodified' => '1756212700',
      'etag' => '9d8f0fb16ca2fe70dad0ce2c1fdd4166',
      'size' => '939',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1745704800',
      'lastoccurence' => '1745744400',
      'uid' => '6ad26e58-6c3a-48bf-9d0b-0579dfee252e',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '640',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//IDN georgehrke.com//calendar-js//EN
CALSCALE:GREGORIAN
BEGIN:VTIMEZONE
TZID:Arctic/Longyearbyen
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:20250421T192458Z
SEQUENCE:2
DTSTART;TZID=Arctic/Longyearbyen:20250421T100000
STATUS:CONFIRMED
DTEND;TZID=Arctic/Longyearbyen:20250422T000000
UID:1bb239f6-6110-4d3f-a84b-ab0c9bfcab64
RELATED-TO;RELTYPE=SIBLING:1ceae044-424c-4984-b851-1c1014262fa3
RELATED-TO;RELTYPE=SIBLING:6ad26e58-6c3a-48bf-9d0b-0579dfee252e
DTSTAMP:20250421T192519Z
LAST-MODIFIED:20250421T192519Z
CATEGORIES:%PROJECT_NAME%,other,Sonstiges
SUMMARY:Sonstiges\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => '5CE69A6C-1EE6-11F0-ADAE-9F26E247CE42.ics',
      'calendarid' => '47',
      'lastmodified' => '1756212699',
      'etag' => '7bb549949c4d235b4c69069e1edb36b8',
      'size' => '939',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1745222400',
      'lastoccurence' => '1745272800',
      'uid' => '1bb239f6-6110-4d3f-a84b-ab0c9bfcab64',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '639',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//IDN georgehrke.com//calendar-js//EN
CALSCALE:GREGORIAN
BEGIN:VTIMEZONE
TZID:Arctic/Longyearbyen
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:20250421T192458Z
SEQUENCE:2
UID:1ceae044-424c-4984-b851-1c1014262fa3
STATUS:CONFIRMED
DTSTART;VALUE=DATE:20250422T000000
DTEND;VALUE=DATE:20250423
RRULE:FREQ=DAILY;INTERVAL=1;UNTIL=20250426
RELATED-TO;RELTYPE=SIBLING:1bb239f6-6110-4d3f-a84b-ab0c9bfcab64
RELATED-TO;RELTYPE=SIBLING:6ad26e58-6c3a-48bf-9d0b-0579dfee252e
DTSTAMP:20250421T192519Z
LAST-MODIFIED:20250421T192519Z
CATEGORIES:%PROJECT_NAME%,other,Sonstiges
SUMMARY:Sonstiges\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => 'D927B3C3-90F5-4A1D-8B52-589A63A3B138.ics',
      'calendarid' => '47',
      'lastmodified' => '1756212700',
      'etag' => '2223a0a5b2b89868f83b23e23aac0f11',
      'size' => '948',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1745280000',
      'lastoccurence' => '1745712000',
      'uid' => '1ceae044-424c-4984-b851-1c1014262fa3',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '636',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//IDN georgehrke.com//calendar-js//EN
BEGIN:VTIMEZONE
TZID:Arctic/Longyearbyen
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:20250307T074711Z
SEQUENCE:2
DTEND;TZID=Arctic/Longyearbyen:20250321T170000
STATUS:CONFIRMED
DESCRIPTION:Hallo Beschreibung
LOCATION:Hallo Ort
DTSTART;TZID=Arctic/Longyearbyen:20250321T000000
UID:7b97c299-7e7c-4ec5-ac58-fd68b90e53db
RELATED-TO;RELTYPE=SIBLING:02fbd55c-4e01-4d46-a1d6-8b7686b1304d
RELATED-TO;RELTYPE=SIBLING:9a372304-33fb-46f5-a3c3-c5730a79cf9a
DTSTAMP:20250308T001438Z
LAST-MODIFIED:20250308T001438Z
CATEGORIES:rehearsals,Proben,Abwesenheit erfassen,%PROJECT_NAME%
SUMMARY:Proben\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => '89D17B5A-FB28-11EF-9900-4D17E2143159.ics',
      'calendarid' => '45',
      'lastmodified' => '1756212698',
      'etag' => '0115208fc360a9833205b3dd1a2cd3be',
      'size' => '1020',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1742511600',
      'lastoccurence' => '1742572800',
      'uid' => '7b97c299-7e7c-4ec5-ac58-fd68b90e53db',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '634',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//IDN georgehrke.com//calendar-js//EN
BEGIN:VEVENT
CREATED:20250307T074711Z
SEQUENCE:2
UID:9a372304-33fb-46f5-a3c3-c5730a79cf9a
STATUS:CONFIRMED
DESCRIPTION:Hallo Beschreibung
LOCATION:Hallo Ort
DTSTART;VALUE=DATE:20250318
DTEND;VALUE=DATE:20250319
RRULE:FREQ=DAILY;UNTIL=20250320
RELATED-TO;RELTYPE=SIBLING:02fbd55c-4e01-4d46-a1d6-8b7686b1304d
RELATED-TO;RELTYPE=SIBLING:7b97c299-7e7c-4ec5-ac58-fd68b90e53db
DTSTAMP:20250307T074820Z
LAST-MODIFIED:20250307T074820Z
CATEGORIES:%PROJECT_NAME%,rehearsals,Proben,Abwesenheit erfassen
SUMMARY:Proben\\, %PROJECT_NAME%
END:VEVENT
BEGIN:VEVENT
CREATED:20250307T074844Z
SEQUENCE:1
UID:9a372304-33fb-46f5-a3c3-c5730a79cf9a
STATUS:CONFIRMED
DESCRIPTION:Hallo Beschreibung
LOCATION:Hallo Ort
DTSTART;VALUE=DATE:20250318
DTEND;VALUE=DATE:20250319
RELATED-TO;RELTYPE=SIBLING:02fbd55c-4e01-4d46-a1d6-8b7686b1304d
RELATED-TO;RELTYPE=SIBLING:7b97c299-7e7c-4ec5-ac58-fd68b90e53db
DTSTAMP:20250308T001441Z
LAST-MODIFIED:20250308T001441Z
RECURRENCE-ID;VALUE=DATE:20250318
CATEGORIES:%PROJECT_NAME%,Proben,Abwesenheit erfassen
SUMMARY:Proben\\, %PROJECT_NAME%
END:VEVENT
BEGIN:VEVENT
CREATED:20250307T074845Z
SEQUENCE:1
UID:9a372304-33fb-46f5-a3c3-c5730a79cf9a
STATUS:CONFIRMED
DESCRIPTION:Hallo Beschreibung
LOCATION:Hallo Ort
DTSTART;VALUE=DATE:20250319
DTEND;VALUE=DATE:20250320
RELATED-TO;RELTYPE=SIBLING:02fbd55c-4e01-4d46-a1d6-8b7686b1304d
RELATED-TO;RELTYPE=SIBLING:7b97c299-7e7c-4ec5-ac58-fd68b90e53db
DTSTAMP:20250308T001432Z
LAST-MODIFIED:20250308T001432Z
RECURRENCE-ID;VALUE=DATE:20250319
CATEGORIES:%PROJECT_NAME%,Proben,Abwesenheit erfassen
SUMMARY:Proben\\, %PROJECT_NAME%
END:VEVENT
BEGIN:VEVENT
CREATED:20250307T074845Z
SEQUENCE:1
UID:9a372304-33fb-46f5-a3c3-c5730a79cf9a
STATUS:CONFIRMED
DESCRIPTION:Hallo Beschreibung
LOCATION:Hallo Ort
DTSTART;VALUE=DATE:20250320
DTEND;VALUE=DATE:20250321
RELATED-TO;RELTYPE=SIBLING:02fbd55c-4e01-4d46-a1d6-8b7686b1304d
RELATED-TO;RELTYPE=SIBLING:7b97c299-7e7c-4ec5-ac58-fd68b90e53db
DTSTAMP:20250308T001434Z
LAST-MODIFIED:20250308T001434Z
RECURRENCE-ID;VALUE=DATE:20250320
CATEGORIES:%PROJECT_NAME%,Proben,Abwesenheit erfassen
SUMMARY:Proben\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => '3C02F464-70B4-4772-99E3-B6A41BFEDA31.ics',
      'calendarid' => '45',
      'lastmodified' => '1756212698',
      'etag' => 'f4eb0ffce07b0b056ca5674ecf159974',
      'size' => '2245',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1742256000',
      'lastoccurence' => '1742515200',
      'uid' => '9a372304-33fb-46f5-a3c3-c5730a79cf9a',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '633',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//IDN georgehrke.com//calendar-js//EN
BEGIN:VEVENT
CREATED:20250306T225707Z
SEQUENCE:2
UID:c3f638fa-118d-4c40-b43a-6c278b6490a3
DTSTART;VALUE=DATE:20250306
DTEND;VALUE=DATE:20250307
STATUS:CONFIRMED
LAST-MODIFIED:20250308T192844Z
DTSTAMP:20250308T192844Z
CATEGORIES:finance,Kasse,%PROJECT_NAME%
SUMMARY:Kasse\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => '30734E6C-487E-4059-9C4E-BFACC83F82AB.ics',
      'calendarid' => '49',
      'lastmodified' => '1756212701',
      'etag' => 'ccf8052d86d04d06e2f25951b7f733d3',
      'size' => '411',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1741219200',
      'lastoccurence' => '1741305600',
      'uid' => 'c3f638fa-118d-4c40-b43a-6c278b6490a3',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '631',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//IDN georgehrke.com//calendar-js//EN
BEGIN:VTIMEZONE
TZID:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:20250304T090336Z
SEQUENCE:2
DTEND;TZID=Europe/Berlin:20250307T170000
STATUS:CONFIRMED
LOCATION:Karlstraße 26\\, 71229 Leonberg
DESCRIPTION:Das ist ein besonderes langer Termin.
DTSTART;TZID=Europe/Berlin:20250307T000000
UID:0007215b-1f0c-4b56-8f0a-70acfc0f63da
RELATED-TO;RELTYPE=SIBLING:fe9c8f84-f2ae-40fb-b56a-779a39b06989
RELATED-TO;RELTYPE=SIBLING:3156a6ba-d4a2-418d-a2da-366d5388632e
DTSTAMP:20250308T202056Z
LAST-MODIFIED:20250308T202056Z
CATEGORIES:finance,%PROJECT_NAME%
SUMMARY:Schöne Zusammenfassung\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => 'C9D92912-F8D7-11EF-892C-3F353C6D504D.ics',
      'calendarid' => '49',
      'lastmodified' => '1756212700',
      'etag' => '4a74325f91b6a10007147deb56c05fdd',
      'size' => '1029',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1741302000',
      'lastoccurence' => '1741363200',
      'uid' => '0007215b-1f0c-4b56-8f0a-70acfc0f63da',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '630',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//IDN georgehrke.com//calendar-js//EN
BEGIN:VTIMEZONE
TZID:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:20250304T090336Z
SEQUENCE:2
DTSTART;TZID=Europe/Berlin:20250304T100000
STATUS:CONFIRMED
LOCATION:Karlstraße 26\\, 71229 Leonberg
DESCRIPTION:Das ist ein besonderes langer Termin.
DTEND;TZID=Europe/Berlin:20250305T000000
UID:fe9c8f84-f2ae-40fb-b56a-779a39b06989
RELATED-TO;RELTYPE=SIBLING:3156a6ba-d4a2-418d-a2da-366d5388632e
RELATED-TO;RELTYPE=SIBLING:0007215b-1f0c-4b56-8f0a-70acfc0f63da
LAST-MODIFIED:20250308T202055Z
DTSTAMP:20250308T202055Z
CATEGORIES:%PROJECT_NAME%,Kasse,finance
SUMMARY:Schöne Zusammenfassung\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => 'C9D7987C-F8D7-11EF-B5E3-8FE1B384B391.ics',
      'calendarid' => '49',
      'lastmodified' => '1756212701',
      'etag' => 'aed538c627533890e5244d7a48832f56',
      'size' => '1021',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1741078800',
      'lastoccurence' => '1741129200',
      'uid' => 'fe9c8f84-f2ae-40fb-b56a-779a39b06989',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '629',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//IDN georgehrke.com//calendar-js//EN
BEGIN:VEVENT
CREATED:20250304T090336Z
SEQUENCE:2
UID:3156a6ba-d4a2-418d-a2da-366d5388632e
STATUS:CONFIRMED
LOCATION:Karlstraße 26\\, 71229 Leonberg
DESCRIPTION:Das ist ein besonderes langer Termin.
DTSTART;VALUE=DATE:20250305
DTEND;VALUE=DATE:20250306
RRULE:FREQ=DAILY;UNTIL=20250306
RELATED-TO;RELTYPE=SIBLING:fe9c8f84-f2ae-40fb-b56a-779a39b06989
RELATED-TO;RELTYPE=SIBLING:0007215b-1f0c-4b56-8f0a-70acfc0f63da
DTSTAMP:20250304T090515Z
LAST-MODIFIED:20250304T090515Z
CATEGORIES:%PROJECT_NAME%,finance
SUMMARY:Schöne Zusammenfassung\\, %PROJECT_NAME%
END:VEVENT
BEGIN:VEVENT
CREATED:20250306T210113Z
SEQUENCE:1
UID:3156a6ba-d4a2-418d-a2da-366d5388632e
STATUS:CONFIRMED
LOCATION:Karlstraße 26\\, 71229 Leonberg
DESCRIPTION:Das ist ein besonderes langer Termin.
DTSTART;VALUE=DATE:20250305
DTEND;VALUE=DATE:20250306
RELATED-TO;RELTYPE=SIBLING:fe9c8f84-f2ae-40fb-b56a-779a39b06989
RELATED-TO;RELTYPE=SIBLING:0007215b-1f0c-4b56-8f0a-70acfc0f63da
RECURRENCE-ID;VALUE=DATE:20250305
SUMMARY:Schöne Zusammenfassung\\, %PROJECT_NAME%
CATEGORIES:%PROJECT_NAME%
LAST-MODIFIED:20250903T093402Z
DTSTAMP:20250903T093402Z
END:VEVENT
BEGIN:VEVENT
CREATED:20250306T210114Z
SEQUENCE:1
UID:3156a6ba-d4a2-418d-a2da-366d5388632e
STATUS:CONFIRMED
LOCATION:Karlstraße 26\\, 71229 Leonberg
DESCRIPTION:Das ist ein besonderes langer Termin.
DTSTART;VALUE=DATE:20250306
DTEND;VALUE=DATE:20250307
RELATED-TO;RELTYPE=SIBLING:fe9c8f84-f2ae-40fb-b56a-779a39b06989
RELATED-TO;RELTYPE=SIBLING:0007215b-1f0c-4b56-8f0a-70acfc0f63da
RECURRENCE-ID;VALUE=DATE:20250306
SUMMARY:Schöne Zusammenfassung\\, %PROJECT_NAME%
CATEGORIES:%PROJECT_NAME%
LAST-MODIFIED:20250903T093402Z
DTSTAMP:20250903T093402Z
END:VEVENT
BEGIN:VEVENT
CREATED:20250306T210114Z
SEQUENCE:1
UID:3156a6ba-d4a2-418d-a2da-366d5388632e
STATUS:CONFIRMED
LOCATION:Karlstraße 26\\, 71229 Leonberg
DESCRIPTION:Das ist ein besonderes langer Termin.
DTSTART;VALUE=DATE:20250306
DTEND;VALUE=DATE:20250307
RELATED-TO;RELTYPE=SIBLING:fe9c8f84-f2ae-40fb-b56a-779a39b06989
RELATED-TO;RELTYPE=SIBLING:0007215b-1f0c-4b56-8f0a-70acfc0f63da
RECURRENCE-ID;VALUE=DATE:20250306
LAST-MODIFIED:20250308T174152Z
DTSTAMP:20250308T174152Z
CATEGORIES:%PROJECT_NAME%
SUMMARY:Schöne Zusammenfassung\\, %PROJECT_NAME%
END:VEVENT
BEGIN:VEVENT
CREATED:20250306T210113Z
SEQUENCE:1
UID:3156a6ba-d4a2-418d-a2da-366d5388632e
STATUS:CONFIRMED
LOCATION:Karlstraße 26\\, 71229 Leonberg
DESCRIPTION:Das ist ein besonderes langer Termin.
DTSTART;VALUE=DATE:20250305
DTEND;VALUE=DATE:20250306
RELATED-TO;RELTYPE=SIBLING:fe9c8f84-f2ae-40fb-b56a-779a39b06989
RELATED-TO;RELTYPE=SIBLING:0007215b-1f0c-4b56-8f0a-70acfc0f63da
RECURRENCE-ID;VALUE=DATE:20250305
LAST-MODIFIED:20250307T101935Z
DTSTAMP:20250307T101935Z
CATEGORIES:%PROJECT_NAME%
SUMMARY:Schöne Zusammenfassung\\, %PROJECT_NAME%
END:VEVENT
BEGIN:VEVENT
CREATED:20250306T210113Z
SEQUENCE:1
UID:3156a6ba-d4a2-418d-a2da-366d5388632e
STATUS:CONFIRMED
LOCATION:Karlstraße 26\\, 71229 Leonberg
DESCRIPTION:Das ist ein besonderes langer Termin.
DTSTART;VALUE=DATE:20250305
DTEND;VALUE=DATE:20250306
RELATED-TO;RELTYPE=SIBLING:fe9c8f84-f2ae-40fb-b56a-779a39b06989
RELATED-TO;RELTYPE=SIBLING:0007215b-1f0c-4b56-8f0a-70acfc0f63da
RECURRENCE-ID;VALUE=DATE:20250305
LAST-MODIFIED:20250903T093348Z
DTSTAMP:20250903T093348Z
CATEGORIES:%PROJECT_NAME%
SUMMARY:Schöne Zusammenfassung\\, %PROJECT_NAME%
END:VEVENT
BEGIN:VEVENT
CREATED:20250306T210114Z
SEQUENCE:1
UID:3156a6ba-d4a2-418d-a2da-366d5388632e
STATUS:CONFIRMED
LOCATION:Karlstraße 26\\, 71229 Leonberg
DESCRIPTION:Das ist ein besonderes langer Termin.
DTSTART;VALUE=DATE:20250306
DTEND;VALUE=DATE:20250307
RELATED-TO;RELTYPE=SIBLING:fe9c8f84-f2ae-40fb-b56a-779a39b06989
RELATED-TO;RELTYPE=SIBLING:0007215b-1f0c-4b56-8f0a-70acfc0f63da
RECURRENCE-ID;VALUE=DATE:20250306
LAST-MODIFIED:20250903T093402Z
DTSTAMP:20250903T093402Z
CATEGORIES:%PROJECT_NAME%,finance
SUMMARY:Schöne Zusammenfassung\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => '952C1F14-E786-48A2-A3EB-13F8AE2C4EE9.ics',
      'calendarid' => '49',
      'lastmodified' => '1756892042',
      'etag' => '8af79adf8b603e8a8615576ca59ade07',
      'size' => '4045',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1741132800',
      'lastoccurence' => '1741305600',
      'uid' => '3156a6ba-d4a2-418d-a2da-366d5388632e',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '626',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//IDN georgehrke.com//calendar-js//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
CREATED:20250216T175625Z
DTSTAMP:20250216T175639Z
LAST-MODIFIED:20250216T175639Z
SEQUENCE:2
UID:3cae242f-b1b5-4e16-8195-707e1ee156ec
DTSTART;TZID=Arctic/Longyearbyen:20250216T185625
DTEND;TZID=Arctic/Longyearbyen:20250216T185625
STATUS:CONFIRMED
CATEGORIES:%PROJECT_NAME%,management
SUMMARY:Vorstand\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => '521BA9B5-18A2-42D5-8E70-7BAD13376E9E.ics',
      'calendarid' => '48',
      'lastmodified' => '1756212700',
      'etag' => 'e3fc5b3f46bea0deb707d397c9e73284',
      'size' => '453',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1739728585',
      'lastoccurence' => '1739728585',
      'uid' => '3cae242f-b1b5-4e16-8195-707e1ee156ec',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '624',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED:20250212T210126Z
UID:faaad3ca-1d14-48cc-a5bc-8594f6570a7e
LAST-MODIFIED:20250212T235928Z
DTSTAMP:20250212T235928Z
DTSTART;VALUE=DATE:20250216
DTEND;VALUE=DATE:20250217
LOCATION:
DESCRIPTION:
SEQUENCE:0
CATEGORIES:Sonstiges,%PROJECT_NAME%
SUMMARY:Sonstiges\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => '863FA79C-E984-11EF-803F-D77076E61BF8.ics',
      'calendarid' => '47',
      'lastmodified' => '1756212700',
      'etag' => '282cccbe2d6b05c97a110552f87605d4',
      'size' => '397',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1739664000',
      'lastoccurence' => '1739750400',
      'uid' => 'faaad3ca-1d14-48cc-a5bc-8594f6570a7e',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '619',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:ownCloud Calendar
BEGIN:VTIMEZONE
TZID:Arctic/Longyearbyen
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:20230804T161040Z
UID:51009caf-5ca5-410c-bede-0fc4f432359c
LAST-MODIFIED:20250308T001710Z
DTSTAMP:20250308T001710Z
DTSTART;TZID=Arctic/Longyearbyen:20240222T101000
DTEND;TZID=Arctic/Longyearbyen:20240222T111000
LOCATION:
DESCRIPTION:
SEQUENCE:1
CATEGORIES:%PROJECT_NAME%,Proben,Abwesenheit erfassen
SUMMARY:Proben\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => '74DF0E58-32E1-11EE-B087-4BC75144380D.ics',
      'calendarid' => '45',
      'lastmodified' => '1756212698',
      'etag' => 'c42a94e50997aaa5006203b63ae5fa90',
      'size' => '804',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1708593000',
      'lastoccurence' => '1708596600',
      'uid' => '51009caf-5ca5-410c-bede-0fc4f432359c',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '618',
      'calendardata' => 'BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED:20230804T161028Z
UID:3d481bd5-9ea4-4b2d-a1c4-9247467456c1
LAST-MODIFIED:20230804T161028Z
DTSTAMP:20230804T161028Z
DTSTART;TZID=Europe/Oslo:20240420T181500
DTEND;TZID=Europe/Oslo:20240420T211500
LOCATION:
DESCRIPTION:
CATEGORIES:%PROJECT_NAME%,Konzerte,Abwesenheit erfassen
SUMMARY:Konzerte\\, %PROJECT_NAME%
END:VEVENT
END:VCALENDAR
',
      'uri' => '6DDC62D6-32E1-11EE-87EF-3D2D258F32B7.ics',
      'calendarid' => '46',
      'lastmodified' => '1756212698',
      'etag' => '0c21f01c9eeadd3bd3471112ff772410',
      'size' => '430',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1713629700',
      'lastoccurence' => '1713640500',
      'uid' => '3d481bd5-9ea4-4b2d-a1c4-9247467456c1',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
    [
      'id' => '617',
      'calendardata' => 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED:20230804T160253Z
UID:bf37e37b-b7f4-4b27-b605-00c64c6d86b9
LAST-MODIFIED:20250927T144637Z
DTSTAMP:20250927T144637Z
LOCATION:CyberSpace
DESCRIPTION:Anmeldezeitraum für das Projekt "%PROJECT_NAME%". Interessierte könne
 n sich während dieser Zeit über den öffentlichen Link https://dev1.home.
 claus-justus-heine.de/apps/cafevdbmembers/registration/%PROJECT_NAME% online bewe
 rben.
CATEGORIES:Projekt-Anmeldung,%PROJECT_NAME%,other
SUMMARY:Bewerbungszeitraum für %PROJECT_NAME%
DTSTART;VALUE=DATE:20250801
DTEND;VALUE=DATE:20260101
TRANSP:OPAQUE
SEQUENCE:0
END:VEVENT
END:VCALENDAR',
      'uri' => '5E61858A-32E0-11EE-8B79-F745B3CCF2A6.ics',
      'calendarid' => '47',
      'lastmodified' => '1758984397',
      'etag' => 'bf19cd4f493ad6a13318a3442b290344',
      'size' => '1374',
      'componenttype' => 'VEVENT',
      'firstoccurence' => '1754006400',
      'lastoccurence' => '1767225600',
      'uid' => 'bf37e37b-b7f4-4b27-b605-00c64c6d86b9',
      'classification' => '0',
      'calendartype' => '0',
      'deleted_at' => null,
    ],
  ];
  /**
   * Substitute the given project name and the calendar ids.
   *
   * @param string $projectName
   *
   * @param array $calendars
   *
   * @param IL10N $l
   *
   * @return array
   */
  public static function getData(string $projectName, array $calendars, IL10N $l): array
  {
    return array_map(
      function(array $row) use ($projectName, $calendars, $l)  {
        $calendarData = $row['calendardata'];
        $calendarData = str_replace('%PROJECT_NAME%', $projectName, $calendarData);
        $found = false;
        foreach ($calendars as $uri => $calendarId) {
          if (str_contains($calendarData, $uri) || str_contains($calendarData, $l->t($uri))) {
            $row['calendarid'] = $calendarId;
            $found = true;
            break;
          }
        }
        if (!$found) {
          print_r($row);
          exit(1);
        }
        $row['calendardata'] = $calendarData;
        return $row;
      },
      self::DATA,
    );
  }
}
