<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
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
    self::registerListener($context, ProjectUpdatedEventListener::class);
    self::registerListener($context, UserLoggedInEventListener::class);
    self::registerListener($context, UserLoggedOutEventListener::class);
    self::registerListener($context, TranslationNotFoundListener::class);
  }

  private static function registerListener(IRegistrationContext $context, $class) {
    $events = $class::EVENT;
    if (!is_array($events)) {
      $events = [ $events ];
    }
    foreach ($events as $event) {
      $context->registerEventListener($class, $event);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
