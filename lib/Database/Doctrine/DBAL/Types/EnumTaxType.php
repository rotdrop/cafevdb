<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Wrapped\MyCLabs\Enum\Enum as EnumType;

/**
 * Tax types.
 *
 * @method static EnumTaxType CORPORATE_INCOME()
 * @method static EnumVCalendarType SALES()
 * @method static EnumVCalendarType VAT()
 */
class EnumTaxType extends EnumType
{
  use \OCA\CAFEVDB\Toolkit\Traits\FakeTranslationTrait;
  public const CORPORATE_INCOME = 'corporate income tax';
  public const SALES = 'sales tax';
  public const VAT = 'VAT';

  /**
   * Just here in order to inject the enum values into the l10n framework.
   *
   * @return void
   */
  protected static function translationHack():void
  {
    self::t(self::CORPORATE_INCOME);
    self::t(self::SALES);
    self::t(self::VAT);
  }
}