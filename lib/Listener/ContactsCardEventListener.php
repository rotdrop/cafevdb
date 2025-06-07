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
use UnexpectedValueException;

use Sabre\VObject;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property;

use OCP\AppFramework\IAppContainer;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

use OCA\DAV\Events\CardCreatedEvent;
use OCA\DAV\Events\CardDeletedEvent;
use OCA\DAV\Events\CardMovedEvent;
use OCA\DAV\Events\CardUpdatedEvent;

use OCA\CAFEVDB\Common\Functions;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationContext as ParticipationContext;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\VCalendarService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ConfigService;

/**
 * Act on newly created events and tasks.
 *
 * @todo Make the CTOR less expensive.
 */
class ContactsCardEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
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
    $appName = $this->appContainer->get('AppName');
    try {
      /** @var EncryptionService $encryptionService */
      $encryptionService = $this->appContainer->get(EncryptionService::class);
      $orchestraGroup = $encryptionService->getConfigValue(ConfigService::USER_GROUP_KEY);
      if (empty($orchestraGroup)) {
        throw new UnexpectedValueException('The orchestra group is not set.');
      }
      $this->entityManager = $this->appContainer->get(EntityManager::class);
      if (!$this->entityManager->bound()) {
        throw new UnexpectedValueException('The entity manager is not bound.');
      }
      $this->entityManager->beginTransaction();
      // https://localhost/nextcloud-git-31/remote.php/dav/addressbooks/users/claus/general_shared_by_cameratashareholder/
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

      // @todo: as the operation are performed "in the background" we should
      // probably generate notifications to inform about the result.
      switch ($eventClass) {
        case CardMovedEvent::class:
          /** @var CardMovedEvent $event */
          // We only update the addressBookUri field here
          $addressBookUri = self::getAddressBookUri($event->getSourceShares());
          $cardData = $event->getObjectData();
          /** @var VCard $vCard */
          $vCard = VObject\Reader::read($cardData['carddata']);
          $musician = $this->getDatabaseRepository(Entities\Musician::class)
            ->findOneBy([
              'uuid' => $vCard->UID,
              'addressBookUri' => $addressBookUri,
            ]);
          if ($musician !== null) {
            if (self::isAddressBookGroupWritable($event->getTargetShares(), $orchestraGroup)) {
              $addressBookUri = self::getAddressBookUri($event->getTargetShares());
            } else {
              $addressBookUri = null;
            }
            $musician->setAddressBookUri($addressBookUri);
            // @todo: change the UUID?
          }
          break;
        case CardDeletedEvent::class:
          /** @var CardDeletedEvent $event */
          // This does NOT trigger deletion of the musician in the database;
          // we just break the link to the addressbook. We do this even if the
          // address book would not be suitable for import in order to reduce
          // the potential for broken addressbook links.
          $addressBookUri = self::getAddressBookUri($event->getAddressBookData());
          $cardData = $event->getCardData();
          /** @var VCard $vCard */
          $vCard = VObject\Reader::read($cardData['carddata']);
          $musician = $this->getDatabaseRepository(Entities\Musician::class)
            ->findOneBy([
              'uuid' => $vCard->UID,
              'addressBookUri' => $addressBookUri,
            ]);
          if ($musician !== null) {
            $musician->setAddressBookUri(null);
          }
          break;
        case CardCreatedEvent::class:
          /** @var CardCreatedEvent $event */
          // fallthrough, for our purposes created or update is just the same.
        case CardUpdatedEvent::class:
          /** @var CardUpdatedEvent $event */
          // For now skip non-shared addressbooks
          $shares = $event->getShares();
          if (!self::isAddressBookGroupWritable($shares, $orchestraGroup)) {
            // avoid privilege escalation or one-way sync
            break;
          }
          $addressBookData = $event->getAddressBookData();
          list(,,$addressBookOwner) = explode('/', $addressBookData['principaluri']);
          $addressBookUri = $addressBookOwner . '/' . $addressBookData['uri'];
          $cardData = $event->getCardData();
          /** @var VCard $vCard */
          $vCard = VObject\Reader::read($cardData['carddata']);
          $categories = VCalendarService::getCategories($vCard);
          $instrumentCategories = $this->getDatabaseRepository(Entities\Instrument::class)->findNames();
          $projectCategories = $this->getDatabaseRepository(Entities\Project::class)->findNames();
          $instrumentCategories = array_intersect($categories, $instrumentCategories);
          $projectCategories = array_intersect($categories, $projectCategories);
          $musician = $this->getDatabaseRepository(Entities\Musician::class)->findByUUID((string)$vCard->UID);
          if ($musician === null) {
            if (in_array($appName, $categories)) {
              // request to be included in the database
              $organization = (string)$vCard->ORG;

              // Generate a matching musician entity. Instruments will be
              // correct, but project-participation has to be established.
              /** @var ContactsService $contactsService */
              $contactsService = $this->appContainer->get(ContactsService::class);
              $musician = $contactsService->importVCard(null, $vCard, preferWork: !empty($organization));
              $musician->setAddressBookUri($addressBookUri);
              $this->persist($musician);
              $this->flush();

              if (!empty($projectCategories)) {
                /** @var ProjectService $projectService */
                $projectService = $this->appContainer->get(ProjectService::class);
                foreach ($projectCategories as $projectName) {
                  $project = $projectService->findByName($projectName);
                  if (empty($project)) {
                    continue;
                  }
                  $status = $projectService->addMusicians(
                    [ $musician->getId() ],
                    $project,
                    $organization ? ParticipationContext::ASSOCIATES : ParticipationContext::PARTICIPANTS,
                  );
                  $this->logInfo('Add Musician Status ' . print_r($status, true));
                }
              }
            }
          } else {
            $this->logInfo('FOUND MUSICIAN WITH ID ' . $musician->getId());
            // should sync ... question is if this should be destructive or
            // just add new instruments and add to new projects ...
            if (in_array($appName, $categories)) {
              $organization = (string)$vCard->ORG;

              // Generate a matching musician entity. Instruments will be
              // correct, but project-participation has to be established.
              /** @var ContactsService $contactsService */
              $contactsService = $this->appContainer->get(ContactsService::class);
              $contactsService->importVCard($musician, $vCard, preferWork: !empty($organization));
              $musician->setAddressBookUri($addressBookUri);

              $musicianProjects = $musician
                ->getProjectParticipation()
                ->map(fn(Entities\ProjectParticipant $participant) => [
                  'name' => $participant->getProject()->getName(),
                  'id' => $participant->getProject()->getId(),
                ])
                ->toArray();
              $musicianProjects = array_combine(
                array_column($musicianProjects, 'name'),
                array_column($musicianProjects, 'id'),
              );
              $missingProjects = array_diff($projectCategories, array_keys($musicianProjects));
              if (!empty($missingProjects)) {
                /** @var ProjectService $projectService */
                $projectService = $this->appContainer->get(ProjectService::class);
                foreach ($missingProjects as $projectName) {
                  $project = $projectService->findByName($projectName);
                  if (empty($project)) {
                    continue;
                  }
                  $status = $projectService->addMusicians(
                    [ $musician->getId() ],
                    $project,
                    $organization ? ParticipationContext::ASSOCIATES : ParticipationContext::PARTICIPANTS,
                  );
                  $this->logInfo('Add Musician Status ' . print_r($status, true));
                }
              }

              $excessProjects = array_diff(array_keys($musicianProjects), $projectCategories);
              if (!empty($excessProjects)) {
                /** @var ProjectService $projectService */
                $projectService = $this->appContainer->get(ProjectService::class);
                foreach ($excessProjects as $projectName) {
                  $projectId = $musicianProjects[$projectName];
                  $projectService->deleteProjectParticipant($musician->getProjectParticipantOf($projectId));
                }
              }

            } else {
              // Arguably we should perhaps delete the musician. We do not,
              // but instead set its address-book uri to NULL.
              $musician->setAddressBookUri(null);
              // @todo: change the UUID?
            }
          }
          break;
      }
      $this->flush();
      $this->entityManager->commit();
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      $this->logException($t);
    }
  }

  /**
   * Check if the given share-info indicates that the address-book is group
   * writable by the given gid:
   * ```
   * [
   *   "href" => "principal:principals/groups/camerata"
   *   "commonName" => "camerata"
   *   "status" => 1
   *   "readOnly" => false
   *   "{http://owncloud.org/ns}principal" => "principals/groups/camerata"
   *   "{http://owncloud.org/ns}group-share" => true
   * ],
   * ```
   *
   * @param array $shareInfo
   *
   * @param string $gid
   *
   * @return bool
   */
  private static function isAddressBookGroupWritable(array $shareInfo, string $gid):bool
  {
    $required = [
      'href' => 'principal:principals/groups/' . $gid,
      'status' => 1,
      'readOnly' => false,
      '{http://owncloud.org/ns}principal' => 'principals/groups/' . $gid,
      '{http://owncloud.org/ns}group-share' => true,
    ];
    foreach ($shareInfo as $share) {
      foreach ($required as $key => $value) {
        if ($share[$key] !== $value) {
          continue;
        }
      }
      return true;
    }
    return false;
  }

  /**
   * @param array $addressBookData
   *
   * @return string
   */
  private static function getAddressBookUri(array $addressBookData):string
  {
    list(,,$addressBookOwner) = explode('/', $addressBookData['principaluri']);
    return $addressBookOwner . '/' . $addressBookData['uri'];
  }
}
