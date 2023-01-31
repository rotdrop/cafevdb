<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2023 Claus-Justus Heine
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
 * Enum for "participant-fields" attribute names.
 *
 * @see \OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantFieldAttribute
 *
 * @method static EnumParticipantFieldAttribute DUE_DATE()
 *   Due-date if this is a monetary field.
 *
 * @method static EnumParticipantFieldAttribute DEPOSIT_DUE_DATE()
 *   Deposit due-date if this is a monetary field and a value for the deposit
 *   has been configured.
 *
 * @method static EnumParticipantFieldAttribute UI_NEGATE_SIGN()
 *   Negate the sign of a monetary value in the UserInterface. This used to
 *   provide a more natural user experience when configuring service-fee
 *   fields vs. reimbursement fields.
 */
class EnumParticipantFieldAttribute extends EnumType
{
  public const DUE_DATE = 'due-date';
  public const DEPOSIT_DUE_DATE = 'deposit-due-date';
  public const NEGATE_DISPLAY_VALUE = 'negate-display-value';
}
