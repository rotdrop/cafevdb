<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Listener;

use Throwable;

use OCP\AppFramework\IAppContainer;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

use OCA\DAV\Events\CardCreatedEvent;
use OCA\DAV\Events\CardDeletedEvent;
use OCA\DAV\Events\CardMovedEvent;
use OCA\DAV\Events\CardUpdatedEvent;

use OCA\CAFEVDB\Common\Functions;

/**
 * Act on newly created events and tasks.
 *
 * @todo Make the CTOR less expensive.
 */
class ContactsCardEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const EVENT = [
    CardCreatedEvent::class,
    CardDeletedEvent::class,
    CardMovedEvent::class,
    CardUpdatedEvent::class,
  ];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected IAppContainer $appContainer,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    $eventClass = get_class($event);
    if (!in_array($eventClass, self::EVENT)) {
      return;
    }
    $this->logger = $this->appContainer->get(LoggerInterface::class);
    try {
      // $this->logInfo('Got event ' . Functions\dump($event));
      // created, deleted, updated:
      // $event->getAddressBookId();
      //   numeric id (db)
      // $event->getAddressBookData();
      // [
      //   "id" => 8
      //   "uri" => "general"
      //   "principaluri" => "principals/users/cameratashareholder"
      //   "{DAV:}displayname" => "Sonstiges"
      //   "{urn:ietf:params:xml:ns:carddav}addressbook-description" => null
      //   "{http://calendarserver.org/ns/}getctag" => 6
      //   "{http://sabredav.org/ns}sync-token" => 6
      //   "{http://nextcloud.com/ns}owner-displayname" => "cameratashareholder"
      // ]
      // $event->getShares();
      // [
      //    [
      //      "href" => "principal:principals/groups/camerata"
      //      "commonName" => "camerata"
      //      "status" => 1
      //      "readOnly" => false
      //      "{http://owncloud.org/ns}principal" => "principals/groups/camerata"
      //      "{http://owncloud.org/ns}group-share" => true
      //    ],
      //    ...
      // ]
      // $event->getCardData();
      // [
      //   "id" => 36
      //   "uri" => "B4FE5C0A-ACD2-41E5-9E8B-CEF6CB3AC316.vcf"
      //   "lastmodified" => 1749193015
      //   "etag" => ""93ef73e0ff01bf34d752bbc6ec63af18""
      //   "size" => 280
      //   "carddata" => "
      //     BEGIN:VCARD
      //     VERSION:4.0
      //     PRODID:-//Nextcloud Contacts v5.3.0-beta1
      //     UID:4098d79f-5827-4197-9b4f-943d2a20b6c2
      //     FN:Test1
      //     ADR;TYPE=HOME:;;;;;;
      //     EMAIL;TYPE=HOME:
      //     TEL;TYPE=HOME,VOICE:
      //     CATEGORIES:Körperschaften
      //     ORG:TestFirma
      //     REV;VALUE=DATE-AND-OR-TIME:20250606T065654Z
      //     END:VCARD
      // "
      // ]
      switch ($eventClass) {
        case CardCreatedEvent::class:
          /** @var CardCreatedEvent $event */
          break;
        case CardDeletedEvent::class:
          /** @var CardDeletedEvent $event */
          break;
        case CardMovedEvent::class:
          /** @var CardMovedEvent $event */
          break;
        case CardUpdatedEvent::class:
          /** @var CardUpdatedEvent $event */
          $this->logInfo('CARD DATA ' . print_r($event->getCardData(), true) . ' ' . $event->getCardData()['carddata']);
          break;
      }
    } catch (Throwable $t) {
      $this->logException($t);
    }
  }
}
