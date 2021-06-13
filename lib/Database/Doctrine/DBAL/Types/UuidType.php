<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\Uuid;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\Doctrine\UuidBinaryType;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Like UuidBinaryType, but implement a more allowing
 * convertToPHPValue() which accepts also string inputs.
 */
class UuidType extends UuidBinaryType
{
  /** {@inheritdoc} */
  public function convertToPHPValue($value, AbstractPlatform $platform)
  {
    if (is_string($value) && strlen($value) == 36) {
      try {
        $uuid = Uuid::fromString($value);
        return $uuid;
      } catch (\InvalidArgumentException $e) {
        // pass through
      }
    }
    return parent::convertToPHPValue($value, $platform);
  }

  /** {@inheritdoc} */
  public function convertToDatabaseValue($value, AbstractPlatform $platform)
  {
    if (is_string($value) && strlen($value) == 16) {
      try {
        return Uuid::fromBytes($value)->getBytes();
      } catch (\InvalidArgumentException $e) {
        // pass through
      }
    }
    return parent::convertToDatabaseValue($value, $platform);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
