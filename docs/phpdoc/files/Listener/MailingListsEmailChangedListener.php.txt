<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022 Claus-Justus Heine
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

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Listen to renamed and deleted events in order to keep the
 * configured document-templates synchronized with the cloud
 * file-system.
 */
class MailingListsEmailChangedListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const EVENT = [
    Events\PostChangeMusicianEmail::class, // fired by Entities\Musician if the principal address changes
    Events\PostRemoveMusicianEmail::class, // fired by Entities\MusicianEmailAddress if an address is removed
    Events\PostPersistMusicianEmail::class, // fired by Entities\MusicianEmailAddress if an address is added
  ];

  /** @var IAppContainer */
  private $appContainer;

  /**
   * @var array
   *
   * Collected subscription requests. These are handled in a post-commit hook.
   */
  private static $subscriptionRequests = [];

  /**
   * @param IAppContainer $appContainer App-container in order to have a leight-weight constructor.
   */
  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Multiple email addresses not yet supported.
   * @todo Perhaps this should be a post-commit hook
   */
  public function handle(Event $event):void
  {
    if (array_search(get_class($event), self::EVENT) === false) {
      return;
    }

    /** @var Events\MusicianEmailEvent $event */
    $musician = $event->getEntity()->getMusician();
    $musicianId = $musician->getId();

    if (empty(self::$subscriptionRequests[$musicianId])) {
      self::$subscriptionRequests[$musicianId] = [
        'musician' => $musician,
        'changed' => null,
        'removed' => [],
        'added' => [],
      ];
    }
    $subscriptionRequest = &self::$subscriptionRequests[$musicianId];

    /** @var Entities\MusicianEmailAddress $oldEmail */
    /** @var Entities\MusicianEmailAddress $newEmail */
    switch (true) {
      case $event instanceof Events\PostChangeMusicianEmail:
        /** @var Events\PostChangeMusicianEmail $event */
        $subscriptionRequest['changed'] = [
          'old' => $event->getOldEmail()->getAddress(),
          'new' => $event->getNewEmail()->getAddress(),
        ];
        break;
      case $event instanceof Events\PostRemoveMusicianEmail:
        /** @var Events\PostRemoveMusicianEmail $event */
        $subscriptionRequest['removed'][] = $event->getEntity()->getAddress();
        break;
      case $event instanceof Events\PostPersistMusicianEmail:
        /** @var Events\PostPersistMusicianEmail $event */
        $subscriptionRequest['added'][] = $event->getEntity()->getAddress();
        break;
    }

    $this->entityManager = $this->appContainer->get(EntityManager::class);

    $this->entityManager->registerPostCommitAction(function() {
      // there may be more than one request, but only the first has an effect
      $subscriptionRequests = self::$subscriptionRequests;
      self::$subscriptionRequests = [];

      if (empty($subscriptionRequests)) {
        return;
      }

      $this->logger = $this->appContainer->get(ILogger::class);

      /** @var ConfigService $configService */
      $configService = $this->appContainer->get(ConfigService::class);
      if (empty($configService)) {
        return;
      }

      /** @var Repositories\ProjectsRepository $projectsRepository */
      $projectsRepository = $this->getDatabaseRepository(Entities\Project::class);
      $listIds = $projectsRepository->fetchMailingListIds();

      $announcementsListId = $configService->getConfigValue('announcementsMailingList');
      $listIds[] = $announcementsListId;
      $listIds = array_filter($listIds);

      if (empty($listIds)) {
        return;
      }

      /** @var MailingListsService $listsService */
      $listsService = $this->appContainer->get(MailingListsService::class);
      if (empty($listsService)) {
        return;
      }

      foreach ($subscriptionRequests as $subscriptionRequest) {
        /** @var Entities\Musician $musician */
        $musician = $subscriptionRequest['musician'];
        unset($subscriptionRequest['musician']);

        $this->logInfo('SUBSCRIPTION REQUESTS ' . print_r($subscriptionRequest, true));

        $oldPrimary = $subscriptionRequest['changed']['old'] ?? $musician->getEmail();
        $newPrimary = $musician->getEmail();

        $defaultPrimarySubscription = [
          MailingListsService::MEMBER_DELIVERY_STATUS => MailingListsService::DELIVERY_STATUS_ENABLED,
          MailingListsService::MEMBER_DISPLAY_NAME => $musician->getPublicName(firstNameFirst: true),
          MailingListsService::SEND_WELCOME_MESSAGE => false,
        ];

        foreach ($listIds as $listId) {
          // step 1: determine the old default for the primary subscription
          $primarySubscription = $defaultPrimarySubscription;
          $subscription = $listsService->getSubscription($listId, $oldPrimary);
          if (!empty($subscription[MailingListsService::ROLE_MEMBER])) {
            $subscription = $subscription[MailingListsService::ROLE_MEMBER];
            $preferences = $listsService->getSubscriptionPreferences($listId, $oldPrimary);
            if (!empty($preferences[MailingListsService::MEMBER_DELIVERY_STATUS])) {
              $primarySubscription[MailingListsService::MEMBER_DELIVERY_STATUS] = $preferences[MailingListsService::MEMBER_DELIVERY_STATUS];
            }
            if (!empty($subscription['display_name'])) {
              $primarySubscription[MailingListsService::MEMBER_DISPLAY_NAME] = $subscription['display_name'];
            }
          } else {
            // this is only the change handler, so new subscriptions should
            // not be initiated here.
            continue;
          }

          $removedAddresses = $subscriptionRequest['removed'];
          $addedAddresses = $subscriptionRequest['added'];

          // for the announcements mailing list new emails addresses are
          // ignored, only the primary address needs to remain subscribed if
          // the old primary address was subscribed.
          if ($listId == $announcementsListId) {
            $removedAddresses = [];
            $addedAddresses = [];
            if ($oldPrimary != $newPrimary) {
              $removedAddresses[] = $oldPrimary;
            }
          }

          // step 2: remove all address which are to be removed
          foreach ($removedAddresses as $removedAddress) {
            $listsService->unsubscribe($listId, $removedAddress, silent: true);
          }

          // step 3: add the old and new primary address to the set of added address to simplify things.
          if (!empty($newPrimary) && array_search($newPrimary, $addedAddresses) === false) {
            $addedAddresses[] = $newPrimary;
          }
          if (array_search($oldPrimary, $removedAddresses) === false
              && array_search($oldPrimary, $addedAddresses) === false) {
            $addedAddresses[] = $oldPrimary;
          }

          // step 4: add or patch all new addresses with the proper delivery status
          foreach ($addedAddresses as $addedAddress) {
            $subscription = $listsService->getSubscription($listId, $addedAddress);
            if (empty($subscription)) {
              // generate a new subscription
              $subscriptionData = $primarySubscription;
              $subscriptionData[MailingListsService::SUBSCRIBER_EMAIL] = $addedAddress;
              if ($addedAddress != $newPrimary) {
                $subscriptionData[MailingListsService::MEMBER_DELIVERY_STATUS] = MailingListsService::DELIVERY_STATUS_DISABLED_BY_USER;
              }
              try {
                $listsService->subscribe($listId, subscriptionData: $subscriptionData);
              } catch (\Throwable $t) {
                $this->logException($t, sprintf('Subscribing email "%1$s" to list "%2$s" failed.', $addedAddress, $listId));
              }
            } else {
              // patch the existing subscription
              $deliveryStatus = ($addedAddress == $newPrimary)
                ? $primarySubscription[MailingListsService::MEMBER_DELIVERY_STATUS]
                : MailingListsService::DELIVERY_STATUS_DISABLED_BY_USER;
              try {
                $listsService->setSubscriptionPreferences($listId, $addedAddress, [
                  MailingListsService::MEMBER_DELIVERY_STATUS => $deliveryStatus,
                ]);
              } catch (\Throwable $t) {
                $this->logException($t, sprintf('Setting delivery status of email "%1$s" in list "%2$s" failed.', $addedAddress, $listId));
              }
            }
          }
        }
      }
    });
  }
}
