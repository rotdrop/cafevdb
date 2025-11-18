<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Controller;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/**
 * Simple setting as enum.
 */
#[TSAttributes\TypeScript]
enum EnumSepaDebitMandateValidationParam: string
{
  use \OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait;

  case MANDATE_PROJECT_ID = 'mandateProjectId';
  case MUSICIAN_ID = 'musicianId';
  case MANDATE_REFERENCE = 'mandateReference';
  case MANDATE_DATE = 'mandateDate';
  case MANDATE_SEQUENCE = 'mandateSequence';
  case MANDATE_LAST_USED_DATE = 'mandateLastUsedDate';
  case MANDATE_NON_RECURRING = 'mandateNonRecurring';
  case BANK_ACCOUNT_SEQUENCE = 'bankAccountSequence';
  case BANK_ACCOUNT_OWNER = 'bankAccountOwner';
  case BANK_ACCOUNT_IBAN = 'bankAccountIBAN';
  case BANK_ACCOUNT_BIC = 'bankAccountBIC';
  case BANK_ACCOUNT_BLZ = 'bankAccountBLZ';
}
