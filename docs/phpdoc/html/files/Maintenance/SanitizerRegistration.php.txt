<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Simplistic registration interface which gives access to a hard-coded list
 * of sanitizers.
 *
 * @see ISanitizer
 */
class SanitizerRegistration
{
  const SANITIZERS = [
    Entities\MusicianEmailAddress::class => [
      Sanitizers\GoogleMailSanitizer::NAME => Sanitizers\GoogleMailSanitizer::class,
    ],
  ];

  /**
   * @param null|string $class The class-name to look up.
   *
   * @return null|array The list of sanitizers for the given class.
   */
  public static function getSanitizers(?string $class):?array
  {
    return self::SANITIZERS[$class] ?? null;
  }

  /** @return array The list of classes for which sanitizers exists. */
  public static function getClasses():array
  {
    return array_keys(self::SANITIZERS);
  }
}
