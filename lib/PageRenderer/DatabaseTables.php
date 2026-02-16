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

/** Constants collection for all database tables. */
class DatabaseTables
{
  /**
   * Hard-coded sequence table name. This relies on
   *
   * @link https://mariadb.com/kb/en/sequence-storage-engine/
   */
  const SEQUENCE_TABLE = 'seq_0_to_100000_step_1';

  const COMPOSITE_PAYMENTS_TABLE = 'CompositePayments';
  const DATABASE_STORAGES_TABLE = 'DatabaseStorages';
  const DATABASE_STORAGE_DIR_ENTRIES_TABLE = 'DatabaseStorageDirEntries';
  const DONATION_RECEIPTS_TABLE = 'DonationReceipts';
  const FIELD_TRANSLATIONS_TABLE = 'TableFieldTranslations';
  const FILES_TABLE = 'Files';
  const INSTRUMENTS_JOIN_TABLE = 'instrument_instrument_family';
  const INSTRUMENTS_TABLE = 'Instruments';
  const INSTRUMENT_FAMILIES_JOIN_TABLE = 'instrument_instrument_family';
  const INSTRUMENT_FAMILIES_TABLE = 'InstrumentFamilies';
  const INSTRUMENT_INSURANCES_TABLE = 'InstrumentInsurances';
  const INSURANCE_BROKERS_TABLE = 'InsuranceBrokers';
  const INSURANCE_RATES_TABLE = 'InsuranceRates';
  const INVOICES_TABLE = 'Invoices';
  const INVOICE_ITEMS_TABLE = 'InvoiceItems';
  const MUSICIANS_TABLE = 'Musicians';
  const MUSICIAN_EMAILS_TABLE = 'MusicianEmailAddresses';
  const MUSICIAN_INSTRUMENTS_TABLE = 'MusicianInstruments';
  const MUSICIAN_PHOTO_JOIN_TABLE = 'MusicianPhoto';
  const PROJECTS_TABLE = 'Projects';
  const PROJECT_INSTRUMENTATION_NUMBERS_TABLE = 'ProjectInstrumentationNumbers';
  const PROJECT_INSTRUMENTS_TABLE = 'ProjectInstruments';
  const PROJECT_PARTICIPANTS_TABLE = 'ProjectParticipants';
  const PROJECT_PARTICIPANT_FIELDS_DATA_TABLE = 'ProjectParticipantFieldsData';
  const PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE = 'ProjectParticipantFieldsDataOptions';
  const PROJECT_PARTICIPANT_FIELDS_TABLE = 'ProjectParticipantFields';
  const PROJECT_PAYMENTS_TABLE = 'ProjectPayments';
  const SENT_EMAILS_TABLE = 'SentEmails';
  const SEPA_BANK_ACCOUNTS_TABLE = 'SepaBankAccounts';
  const SEPA_BULK_TRANSACTIONS_TABLE = 'SepaBulkTransactions';
  const SEPA_BULK_TRANSACTION_DATA_TABLE = 'SepaBulkTransactionData';
  const SEPA_DEBIT_MANDATES_TABLE = 'SepaDebitMandates';
  const TAXATION_STATUTORY_SOURCES_TABLE = 'TaxationStatutorySources';
  const TAX_EXEMPTION_ITEMS_TABLE = 'TaxExemptionItems';
  const TAX_EXEMPTION_NOTICES_TABLE = 'TaxExemptionNotices';
}
