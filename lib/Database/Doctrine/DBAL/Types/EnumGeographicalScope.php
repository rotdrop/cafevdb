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

use OCA\CAFEVDB\Wrapped\MyCLabs\Enum\Enum as EnumType;

/**
 * Geographical scope for insurances.
 *
 * @method static EnumGeographicalScope DOMESTIC()
 * @method static EnumGeographicalScope CONTINENT()
 * @method static EnumGeographicalScope GERMANY()
 * @method static EnumGeographicalScope EUROPE()
 * @method static EnumGeographicalScope WORLD()
 *
 * @todo Perhaps should be renamed to "COUNTRY/CONTINENT/WORLD"
 */
class EnumGeographicalScope extends EnumType
{
  use \OCA\CAFEVDB\Traits\FakeTranslationTrait;

  public const DOMESTIC = 'Domestic';
  public const CONTINENT = 'Continent';
  public const GERMANY = 'Germany';
  public const EUROPE = 'Europe';
  public const WORLD = 'World';

  static private function translationHack()
  {
    self::t('Domestic');
    self::t('Continent');
    self::t('Germany');
    self::t('Europe');
    self::t('World');
  }
}
