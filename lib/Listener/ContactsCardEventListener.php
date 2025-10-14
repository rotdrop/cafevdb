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
use OCP\IAddressBook;
use Psr\Log\LoggerInterface;

use OCA\DAV\Events\CardCreatedEvent;
use OCA\DAV\Events\CardDeletedEvent;
use OCA\DAV\Events\CardMovedEvent;
use OCA\DAV\Events\CardUpdatedEvent;

use OCA\CAFEVDB\Common\Functions;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationContext as ParticipationContext;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\CardDavService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\VCalendarService;

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

  /**
   * @var array
   *
   * In order to avoid ping-pong the handler runs only once per contact in
   * each request. This might be neccessary as we may modify the linked
   * musician entities here which in turn may trigger modifications of the
   * contact; we also may modify the given contact here which then would
   * recurse into this handler.
   */
  private array $alreadyHandled = [];

  /**
   * @var bool
   *
   * Prevent ping-pong when updating contacts programmatically. This is set
   * from outside and prevents the execution of the handler if set to \false.
   */
  private bool $enabled = true;

  /**
   * @var bool
   *
   * Prevent ping-pong when updating contacts programmatically. This is set to
   * \true when the handler is running and can be queried from outside with
   * isActive().
   */
  private bool $active = false;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected IAppContainer $appContainer,
    private bool $isCLI,
  ) {
  }
  // phpcs:enable

  /**
   * In order to disable the listener during programmatic contact updates.
   *
   * @param bool $enabled
   *
   * @return bool Old state.
   */
  public function setEnabled(bool $enabled):bool
  {
    $oldEnabled = $this->enabled;

    $this->enabled = $enabled;

    return $oldEnabled;
  }

  /**
   * @return bool Whether the handler has been entered.
   */
  public function isActive():bool
  {
    return $this->active;
  }

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    $eventClass = get_class($event);
    if (!$this->enabled || !in_array($eventClass, self::EVENT)) {
      return;
    }
    $this->logger = $this->appContainer->get(LoggerInterface::class);
    $appName = $this->appContainer->get('AppName');
    try {
      /** @var EncryptionService $encryptionService */
      $encryptionService = $this->appContainer->get(EncryptionService::class);
      $orchestraGroup = $encryptionService->getConfigValue(ConfigService::USER_GROUP_KEY);
      if (empty($orchestraGroup)) {
        if (!$this->isCLI) {
          // Do not complain in CLI mode, as of now we have to run unauthenticated.
          throw new UnexpectedValueException('The orchestra group is not set.');
        }
        return;
      }
      $this->active = true;
      $this->entityManager = $this->appContainer->get(EntityManager::class);
      if (!$this->entityManager->bound()) {
        throw new UnexpectedValueException('The entity manager is not bound.');
      }
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
            $this->entityManager->beginTransaction();
            $musician->setAddressBookUri($addressBookUri);
            $this->flush();
            $this->entityManager->commit();

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
            $this->entityManager->beginTransaction();
            $musician->setAddressBookUri(null);
            $this->flush();
            $this->entityManager->commit();
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
          $cardUri = $cardData['uri'];
          if (!empty($this->alreadyHandled[$addressBookUri][$cardUri])) {
            $this->logDebug('Avoid event recursion ' . $addressBookUri . '->' . $cardUri);
            break;
          }
          if (empty($this->alreadyHandled[$addressBookUri])) {
            $this->alreadyHandled[$addressBookUri] = [];
          }
          $this->alreadyHandled[$addressBookUri][$cardUri] = true;

          $this->entityManager->beginTransaction();

          /** @var VCard $vCard */
          $vCard = VObject\Reader::read($cardData['carddata']);
          $categories = VCalendarService::getCategories($vCard);
          $projectCategories = $this->getDatabaseRepository(Entities\Project::class)->findNames();
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
            if (in_array($appName, $categories)) {

              // we actually may change parts of the contact, so clear the etag
              $this->logDebug('CLEARING ETAG');
              $event->clearEtag();

              $organization = (string)$vCard->ORG;
              $preferWork = !empty($organization);

              // if this re-establishes a previously broken link, then we
              // should try an intelligent merge ... instead we give the data
              // stored in our database precedence.
              $keepExisting = empty($musician->getAddressBookUri());

              // Generate a matching musician entity. Instruments will be
              // correct, but project-participation has to be established.
              /** @var ContactsService $contactsService */
              $contactsService = $this->appContainer->get(ContactsService::class);
              $contactsService->importVCard(
                $musician,
                $vCard,
                preferWork: $preferWork,
                keepExisting: $keepExisting,
              );
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

              if ($keepExisting) {
                $addressBook = $contactsService->addressBookByUri($addressBookUri);
                if (!empty($addressBook)) {
                  $contactCard = $contactsService->findMusicianContact($musician);
                  if (empty($contactCard)) {
                    $this->logError(
                      'Broken address-book link for musician "' . $musician->getPublicName(). '"'
                      . ', addressbook "' . $musician->getAddressBookUri() . '"'
                      . ', uuid "' . $musician->getUuid() . '".',
                    );
                  } else {
                    $newCard = $contactsService->mergeMusician($contactCard, $musician);
                    $addressBook->createOrUpdate($newCard);
                  }
                } else {
                  $this->logError('CANNOT FIND ADDRESSBOOK ' . $addressBookUri);
                }
              } else {
                $excessProjects = array_diff(array_keys($musicianProjects), $projectCategories);
                if (!empty($excessProjects)) {
                /** @var ProjectService $projectService */
                  $projectService = $this->appContainer->get(ProjectService::class);
                  foreach ($excessProjects as $projectName) {
                    $projectId = $musicianProjects[$projectName];
                    $projectService->deleteProjectParticipant($musician->getProjectParticipantOf($projectId));
                  }
                }
              }

            } else {
              // Arguably we should perhaps delete the musician. We do not,
              // but instead set its address-book uri to NULL.
              $uri = $musician->getAddressBookUri();
              $musician->setAddressBookUri(null);
              $this->logDebug('Unlinking ' . $musician->getPublicName() . ' from ' . $uri);
              // @todo: change the UUID?
            }
          }
          $this->flush();
          $this->entityManager->commit();
          $this->alreadyHandled[$addressBookUri][$cardUri] = false;
          break;
      }
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->pushTransactionException($t);
        $this->entityManager->rollback();
      } else {
        $this->logException($t);
      }
    }
    $this->active = false;
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
