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

namespace OCA\CAFEVDB\EmailForm;

/** Valid keys for ${GLOBAL::KEY} substitutions. */
enum EnumMemberSubstitutionKey: string
{
  use \OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Traits\TranslatableEnumTrait;

  case FIRST_NAME = 'FIRST_NAME';
  case SUR_NAME = 'SUR_NAME';
  case NICK_NAME = 'NICK_NAME';
  case GENDER = 'GENDER';
  case SALUTATION = 'SALUTATION';
  case DISPLAY_NAME = 'DISPLAY_NAME';
  case EMAIL = 'EMAIL';
  case MOBILE_PHONE = 'MOBILE_PHONE';
  case FIXED_LINE_PHONE = 'FIXED_LINE_PHONE';
  case STREET = 'STREET';
  case STREET_NUMBER = 'STREET_NUMBER';
  case STREET_AND_NUMBER = 'STREET_AND_NUMBER';
  case POSTAL_CODE = 'POSTAL_CODE';
  case CITY = 'CITY';
  case COUNTRY = 'COUNTRY';
  case LANGUAGE = 'LANGUAGE';
  case BIRTHDAY = 'BIRTHDAY';
  case TOTAL_FEES = 'TOTAL_FEES';
  case AMOUNT_PAID = 'AMOUNT_PAID';
  case MISSING_AMOUNT = 'MISSING_AMOUNT';
  case PROJECT_DATA = 'PROJECT_DATA';
  case SEPA_MANDATE_REFERENCE = 'SEPA_MANDATE_REFERENCE';
  case SEPA_MANDATE_DATE = 'SEPA_MANDATE_DATE';
  case BANK_ACCOUNT_IBAN = 'BANK_ACCOUNT_IBAN';
  case BANK_ACCOUNT_BIC = 'BANK_ACCOUNT_BIC';
  case BANK_ACCOUNT_BANK = 'BANK_ACCOUNT_BANK';
  case BANK_ACCOUNT_OWNER = 'BANK_ACCOUNT_OWNER';
  case BANK_TRANSACTION_AMOUNT = 'BANK_TRANSACTION_AMOUNT';
  case BANK_TRANSACTION_PURPOSE = 'BANK_TRANSACTION_PURPOSE';
  case BANK_TRANSACTION_PARTS = 'BANK_TRANSACTION_PARTS';
  case INVOICE_NUMBER = 'INVOICE_NUMBER';
  case INVOICE_DATE = 'INVOICE_DATE';
  case INVOICE_DUE_DATE = 'INVOICE_DUE_DATE';
  case INVOICE_AMOUNT = 'INVOICE_AMOUNT';
  case INVOICE_SUBJECT = 'INVOICE_SUBJECT';
  case INVOICE_PURPOSE = 'INVOICE_PURPOSE';
  case INVOICE_ITEMS = 'INVOICE_ITEMS';
  case DONATION_AMOUNT = 'DONATION_AMOUNT';
  case WAIVED_AMOUNT = 'WAIVED_AMOUNT';
  case WAIVING_OF_REIMBURSEMENT = 'WAIVING_OF_REIMBURSEMENT';
  case DONATION_RECEIPT_DATE = 'DONATION_RECEIPT_DATE';
  case DONATION_PARTS = 'DONATION_PARTS';
  case DATE = 'DATE';

  /** @return EnumSubstitutionNamespace */
  public static function namespace(): EnumSubstitutionNamespace
  {
    return EnumSubstitutionNamespace::MEMBER;
  }
}
