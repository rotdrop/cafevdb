<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
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

namespace OCA\CAFEVDB\PageRenderer;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/**
 * Flat collection of existing template name , not least to have the list of
 * amissible template names available in the TypeScript code.
 */
#[TSAttributes\TypeScript(options: ['nativeEnums' => true])]
enum EnumTemplate: string
{
  use \OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait;

  case ADD_MUSICIANS = 'add-musicians';
  case ALL_MUSICIANS = 'all-musicians';
  case BLOG = 'blog/blog';
  case CONFIG_CHECK = 'maintenance/configcheck';
  case DONATION_RECEIPTS = 'donation-receipts';
  case INSTRUMENT_FAMILIES = 'instrument-families';
  case INSTRUMENT_INSURANCES = 'instrument-insurance';
  case INSTRUMENTS = 'instruments';
  case INSURANCE_BROKERS = 'insurance-brokers';
  case INSURANCE_RATES = 'insurance-rates';
  case INVOICES = 'invoices';
  case PROJECT_ASSOCIATES = 'project-associates';
  case PROJECT_INSTRUMENTATION_NUMBERS = 'project-instrumentation-numbers';
  case PROJECT_PARTICIPANT_FIELDS = 'project-participant-fields';
  case PROJECT_PARTICIPANTS = 'project-participants';
  case PROJECT_PAYMENTS = 'project-payments';
  case PROJECTS = 'projects';
  case SEPA_BANK_ACCOUNTS = 'sepa-bank-accounts';
  case SEPA_BULK_TRANSACTIONS = 'sepa-bulk-transactions';
  case TAX_EXEMPTION_NOTICES = 'tax-exemption-notices';
  case TAXATION_STATUTORY_SOURCES = 'taxation-statutory-sources';
}
