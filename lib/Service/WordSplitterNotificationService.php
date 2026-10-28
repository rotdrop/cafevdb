<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author GitHub Copilot <223556219+Copilot@users.noreply.github.com>
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use DateTime;

use OCP\IGroupManager;
use OCP\Notification\IManager as NotificationManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Notifications\Notifier;

/**
 * Notify the cloud admins and the group admins of the orchestra group about
 * locale problems of the word splitter service.
 */
class WordSplitterNotificationService
{
  /** Problem: the running system-wide service lacks the app locale. */
  public const PROBLEM_SYSTEM_SERVICE = 'system';

  /** Problem: the app locale is not installed, no local service can be started. */
  public const PROBLEM_LOCAL_SERVICE = 'local';

  /** @var string Object type of the notifications, used for de-duplication. */
  public const OBJECT_TYPE = 'word_splitter_locale';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private string $appName,
    private ContainerInterface $appContainer,
    private NotificationManager $notificationManager,
    private IGroupManager $groupManager,
    private ILogger $logger,
  ) {
  }
  // phpcs:enable

  /**
   * Send a single notification describing all given problems to all
   * recipients. A recipient which already has an unprocessed notification for
   * the same set of problems is skipped.
   *
   * @param string $appLocale The locale the service needs.
   *
   * @param string[] $problems Subset of the PROBLEM_* constants.
   *
   * @return void
   */
  public function notifyLocaleProblems(string $appLocale, array $problems): void
  {
    $problems = array_values(array_unique($problems));
    sort($problems);
    if (empty($problems)) {
      return;
    }

    $notification = $this->notificationManager->createNotification();
    $notification->setApp($this->appName)
      ->setDateTime(new DateTime)
      ->setObject(self::OBJECT_TYPE, implode(',', $problems))
      ->setSubject(Notifier::WORD_SPLITTER_LOCALE_SUBJECT, [
        'locale' => $appLocale,
        'problems' => $problems,
      ]);

    foreach ($this->recipients() as $userId) {
      $notification->setUser($userId);
      if ($this->notificationManager->getCount($notification) > 0) {
        continue;
      }
      $this->notificationManager->notify($notification);
    }
  }

  /**
   * @return string[] The user ids of the cloud admins and the group admins.
   */
  private function recipients(): array
  {
    $userIds = [];
    $adminGroup = $this->groupManager->get('admin');
    foreach ($adminGroup?->getUsers() ?? [] as $user) {
      $userIds[$user->getUID()] = true;
    }
    try {
      /** @var OrganizationalRolesService $rolesService */
      $rolesService = $this->appContainer->get(OrganizationalRolesService::class);
      foreach ($rolesService->getGroupAdmins() as $user) {
        $userIds[$user->getUID()] = true;
      }
    } catch (\Throwable $t) {
      // e.g. no orchestra group configured yet, the cloud admins are still notified
      $this->logger->warning('Unable to determine the group admins for the word splitter notification.', [ 'exception' => $t ]);
    }
    return array_map('strval', array_keys($userIds));
  }
}
