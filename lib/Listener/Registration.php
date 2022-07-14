<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

class Registration
{
  public static function register(IRegistrationContext $context) {
    self::registerListener($context, CalendarDeletedEventListener::class);
    self::registerListener($context, CalendarObjectCreatedEventListener::class);
    self::registerListener($context, CalendarObjectDeletedEventListener::class);
    self::registerListener($context, CalendarObjectUpdatedEventListener::class);
    self::registerListener($context, CalendarDeletedEventListener::class);
    self::registerListener($context, CalendarUpdatedEventListener::class);
    self::registerListener($context, PasswordUpdatedEventListener::class);
    self::registerListener($context, ProjectDeletedEventListener::class);
    self::registerListener($context, PreProjectUpdatedEventListener::class);
    // self::registerListener($context, PostProjectUpdatedEventListener::class);
    self::registerListener($context, UserLoggedInEventListener::class);
    self::registerListener($context, UserLoggedOutEventListener::class);
    self::registerListener($context, TranslationNotFoundListener::class);
    self::registerListener($context, FileNodeListener::class);
    self::registerListener($context, PreChangeUserIdSlugListener::class);
    self::registerListener($context, PreRenameProjectParticipantFieldListener::class);
    self::registerListener($context, PreChangeProjectParticipantFieldTooltipListener::class);
    self::registerListener($context, PreRenameProjectParticipantFieldOptionListener::class);
    self::registerListener($context, PrePersistProjectParticipantFieldListener::class);
    self::registerListener($context, PreRemoveProjectParticipantFieldListener::class);
    self::registerListener($context, BeforeEncryptionKeyPairChangedListener::class);
    self::registerListener($context, AfterEncryptionKeyPairChangedListener::class);
    self::registerListener($context, SubAdminEventListener::class);
    self::registerListener($context, MailingListsAutoResponsesListener::class);
    self::registerListener($context, MailingListsEmailChangedListener::class);
    self::registerListener($context, MailingListsRegistrationConfirmationListener::class);
    self::registerListener($context, FilesHooksListener::class);
  }

  private static function registerListener(IRegistrationContext $context, $class) {
    $events = $class::EVENT;
    if (!is_array($events)) {
      $events = [ $events ];
    }
    foreach ($events as $event) {
      $context->registerEventListener($event, $class);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
