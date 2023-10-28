<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Listener;

use OCP\AppFramework\Bootstrap\IRegistrationContext;

/** Helper class for listener registration.
 *
 * @see OCA\CAFEVDB\AppInfo\Application
 */
class Registration
{
  /**
   * @param IRegistrationContext $context
   *
   * @return void
   */
  public static function register(IRegistrationContext $context):void
  {
    self::registerListener($context, CalendarDeletedEventListener::class);
    self::registerListener($context, CalendarObjectCreatedEventListener::class);
    self::registerListener($context, CalendarObjectDeletedEventListener::class);
    self::registerListener($context, CalendarObjectUpdatedEventListener::class);
    self::registerListener($context, CalendarObjectMovedEventListener::class);
    self::registerListener($context, CalendarDeletedEventListener::class);
    self::registerListener($context, CalendarUpdatedEventListener::class);
    self::registerListener($context, PasswordUpdatedEventListener::class);
    self::registerListener($context, ProjectDeletedEventListener::class);
    self::registerListener($context, PreProjectUpdatedEventListener::class);
    // The PostProjectUpdatedEvent is actually used in the cafevdbmembers app.
    // self::registerListener($context, PostProjectUpdatedEventListener::class);
    self::registerListener($context, UserLoggedInEventListener::class);
    self::registerListener($context, UserLoggedOutEventListener::class);
    self::registerListener($context, TranslationNotFoundListener::class);
    self::registerListener($context, FileNodeListener::class);
    self::registerListener($context, ParticipantFieldCloudFolderListener::class);
    self::registerListener($context, BeforeEncryptionKeyPairChangedListener::class);
    self::registerListener($context, AfterEncryptionKeyPairChangedListener::class);
    self::registerListener($context, SubAdminEventListener::class);
    self::registerListener($context, MailingListsAutoResponsesListener::class);
    self::registerListener($context, MailingListsEmailChangedListener::class);
    self::registerListener($context, MailingListsRegistrationConfirmationListener::class);
    self::registerListener($context, FilesHooksListener::class);
    self::registerListener($context, MusicianEmailPersistanceListener::class);
    self::registerListener($context, SepaBulkTransactionSubmittedListener::class);
    self::registerListener($context, SepaBulkTransactionAnnouncedListener::class);
    self::registerListener($context, GroupMembershipListener::class);
  }

  /**
   * @param IRegistrationContext $context
   *
   * @param string $class
   *
   * @return void
   */
  private static function registerListener(IRegistrationContext $context, string $class)
  {
    $events = $class::EVENT;
    if (!is_array($events)) {
      $events = [ $events ];
    }
    foreach ($events as $event) {
      $context->registerEventListener($event, $class);
    }
  }
}
