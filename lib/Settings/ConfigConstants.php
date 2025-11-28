<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Settings;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/**
 * Configuration constants. Ideally all settings keys and some other stuff
 * should be collected in this class. The idea is to not have to fetch the
 * ConfigService just in order to access some constants.
 */
#[TSAttributes\TypeScript]
class ConfigConstants
{
  use \OCA\CAFEVDB\Toolkit\Traits\FakeTranslationTrait;

  public const DEBUG_GENERAL   = (1 << 0);
  public const DEBUG_QUERY     = (1 << 1);
  public const DEBUG_CSP       = (1 << 2);
  public const DEBUG_L10N      = (1 << 3);
  public const DEBUG_REQUEST   = (1 << 4);
  public const DEBUG_TOOLTIPS  = (1 << 5);
  public const DEBUG_EMAILFORM = (1 << 6);
  public const DEBUG_GEOCODING = (1 << 7);
  public const DEBUG_VUE       = (1 << 8);
  public const DEBUG_SMAPS     = (1 << 9);
  public const DEBUG_ALL       = self::DEBUG_GENERAL
    |self::DEBUG_QUERY
    |self::DEBUG_CSP
    |self::DEBUG_L10N
    |self::DEBUG_REQUEST
    |self::DEBUG_TOOLTIPS
    |self::DEBUG_EMAILFORM
    |self::DEBUG_GEOCODING
    |self::DEBUG_VUE
    |self::DEBUG_SMAPS
    ;
  public const DEBUG_NONE      = 0;

  public const DEFAULT_LOCALE = 'en_US';

  public const APP_LOGO = 'logo-greyf.svg';

  /*-**************************************************************************
   *
   * Some configuration constants
   *
   */
  public const SHAREOWNER_KEY = 'shareowner';
  public const SHAREOWNER_FOLDER_SERVICE_KEY = 'shareowner_folder';
  public const SHAREOWNER_CALENDAR_SERVICE_KEY = 'shareowner_calendar';
  public const SHAREOWNER_ADDRESSBOOK_SERVICE_KEY = 'shareowner_addressbook';

  public const SHARED_FOLDER = 'sharedfolder';
  public const PROJECTS_FOLDER = 'projectsfolder';
  public const PROJECT_PARTICIPANTS_FOLDER = 'projectparticipantsfolder';
  public const PROJECT_POSTERS_FOLDER = 'projectpostersfolder';
  public const PROJECT_PUBLIC_DOWNLOADS_FOLDER = 'projectpublicdownloadsfolder';
  public const FINANCE_FOLDER = 'financefolder';
  public const BALANCES_FOLDER = 'balancesfolder';
  public const TRANSACTIONS_FOLDER = 'transactionsfolder';
  public const DOCUMENT_TEMPLATES_FOLDER = 'documenttemplatesfolder';
  public const POSTBOX_FOLDER = 'postboxfolder';
  public const OUTBOX_FOLDER = 'outboxfolder';
  public const PROJECT_SKELETON_FOLDER = 'skeleton';
  public const PROJECT_PARTICIPANTS_SKELETON_FOLDER = 'forename.surname';
  public const PROJECT_MANAGEMENT_SKELETON_FOLDER = 'management';

  public const DEDICATED_FOLDERS = [
    self::SHARED_FOLDER,
    self::PROJECTS_FOLDER,
    self::PROJECT_PARTICIPANTS_FOLDER,
    self::PROJECT_POSTERS_FOLDER,
    self::PROJECT_PUBLIC_DOWNLOADS_FOLDER,
    self::FINANCE_FOLDER,
    self::BALANCES_FOLDER,
    self::TRANSACTIONS_FOLDER,
    self::DOCUMENT_TEMPLATES_FOLDER,
    self::POSTBOX_FOLDER,
    self::OUTBOX_FOLDER,
    self::PROJECT_SKELETON_FOLDER,
    self::PROJECT_PARTICIPANTS_SKELETON_FOLDER,
    self::PROJECT_MANAGEMENT_SKELETON_FOLDER,
  ];

  public const CMS_CATEGORIES = [
    'preview',
    'archive',
    'rehearsals',
    'trashbin',
  ];
  public const CMS_MODULES = [
    'concert',
    'rehearsals',
  ];
  public const CMS_TEMPLATES = [
    'sub-page',
  ];
  public const WYSIWYG_EDITORS = [
    'tinymce' => [ 'name' => 'TinyMCE', 'enabled' => true],
    // ckeditor still uses excessive inline js-code. So what?
    'ckeditor' => [ 'name' => 'CKEditor', 'enabled' => true],
  ];
  public const CONCERTS_CALENDAR_URI = 'concerts';
  public const REHEARSALS_CALENDAR_URI = 'rehearsals';
  public const OTHER_CALENDAR_URI = 'other';
  public const MANAGEMENT_CALENDAR_URI = 'management';
  public const FINANCE_CALENDAR_URI = 'finance';
  public const CALENDARS = [
    self::CONCERTS_CALENDAR_URI => [ 'uri' => self::CONCERTS_CALENDAR_URI, 'public' => true ],
    self::REHEARSALS_CALENDAR_URI => [ 'uri' => self::REHEARSALS_CALENDAR_URI, 'public' => true ],
    self::OTHER_CALENDAR_URI => [ 'uri' => self::OTHER_CALENDAR_URI, 'public' => true ],
    self::MANAGEMENT_CALENDAR_URI => [ 'uri' => self::MANAGEMENT_CALENDAR_URI, 'public' => false ],
    self::FINANCE_CALENDAR_URI => [ 'uri' => self::FINANCE_CALENDAR_URI, 'public' => false ],
  ];

  public const STREET_ADDRESS_PREFIX = 'streetAddress';
  public const STREET_ADDRESS_NAME_01 = self::STREET_ADDRESS_PREFIX . 'Name01';
  public const STREET_ADDRESS_NAME_02 = self::STREET_ADDRESS_PREFIX . 'Name02';
  public const STREET_ADDRESS_STREET = self::STREET_ADDRESS_PREFIX . 'Street';
  public const STREET_ADDRESS_HOUSE_NUMBER = self::STREET_ADDRESS_PREFIX . 'HouseNumber';
  public const STREET_ADDRESS_ZIP = self::STREET_ADDRESS_PREFIX . 'ZIP';
  public const STREET_ADDRESS_CITY = self::STREET_ADDRESS_PREFIX . 'City';
  public const STREET_ADDRESS_COUNTRY = self::STREET_ADDRESS_PREFIX . 'Country';

  public const BANK_ACCOUNT_OWNER = 'bankAccountOwner';
  public const BANK_ACCOUNT_IBAN = 'bankAccountIBAN';
  public const BANK_ACCOUNT_BLZ = 'bankAccountBLZ';
  public const BANK_ACCOUNT_BIC = 'bankAccountBIC';
  public const BANK_ACCOUNT_BANK_NAME = 'bankAccountBankName';
  public const BANK_ACCOUNT_CREDITOR_IDENTIFIER = 'bankAccountCreditorIdentifier';
  public const BANK_ACCOUNT_BANK_HOLIDAYS = 'bankAccountBankHolidays';

  public const BANK_ACCOUNT_CONFIG_KEYS = [
    self::BANK_ACCOUNT_OWNER,
    self::BANK_ACCOUNT_IBAN,
    self::BANK_ACCOUNT_BLZ,
    self::BANK_ACCOUNT_BIC,
    self::BANK_ACCOUNT_BANK_NAME,
    self::BANK_ACCOUNT_CREDITOR_IDENTIFIER,
    self::BANK_ACCOUNT_BANK_HOLIDAYS
  ];

  public const DOCUMENT_TYPE_CONSTANT = 'constant';
  public const DOCUMENT_TYPE_TEMPLATE = 'template';

  public const DOCUMENT_TEMPLATE_LOGO = 'logo';
  public const DOCUMENT_TEMPLATE_LOGO_NAME = 'orchestra logo';
  public const DOCUMENT_TEMPLATE_SEAL = 'seal';
  public const DOCUMENT_TEMPLATE_SEAL_NAME = 'orchestra seal';
  public const DOCUMENT_TEMPLATE_STANDARD_LETTER = 'standardLetter';
  public const DOCUMENT_TEMPLATE_STANDARD_LETTER_NAME = 'standard letter';
  public const DOCUMENT_TEMPLATE_INSTRUMENT_INSURANCE_RECORD = 'instrumentInsuranceRecord';
  public const DOCUMENT_TEMPLATE_INSTRUMENT_INSURANCE_RECORD_NAME = 'instrument insurance record template';
  public const DOCUMENT_TEMPLATE_PROJECT_DEBIT_NOTE_MANDATE = 'projectDebitNoteMandateForm';
  public const DOCUMENT_TEMPLATE_PROJECT_DEBIT_NOTE_MANDATE_NAME = 'project debit-note mandate';
  public const DOCUMENT_TEMPLATE_GENERAL_DEBIT_NOTE_MANDATE = 'generalDebitNoteMandateForm';
  public const DOCUMENT_TEMPLATE_GENERAL_DEBIT_NOTE_MANDATE_NAME = 'general debit-note mandate';
  public const DOCUMENT_TEMPLATE_MEMBER_DATA_UPDATE = 'memberDataUpdateForm';
  public const DOCUMENT_TEMPLATE_MEMBER_DATA_UPDATE_NAME = 'member data update';
  public const DOCUMENT_TEMPLATE_INVOICE = 'invoice';
  public const DOCUMENT_TEMPLATE_INVOICE_NAME = 'invoice';
  public const DOCUMENT_TEMPLATE_STANDARD_RECEIPT = 'standardReceipt';
  public const DOCUMENT_TEMPLATE_STANDARD_RECEIPT_NAME = 'standard receipt';
  public const DOCUMENT_TEMPLATE_DONATION_RECEIPT = 'donationReceipt';
  public const DOCUMENT_TEMPLATE_DONATION_RECEIPT_NAME = 'donation receipt';

  /** @var Dedicated document-templates used in various places. */
  public const DOCUMENT_TEMPLATES = [
    self::DOCUMENT_TEMPLATE_LOGO => [
      'name' => self::DOCUMENT_TEMPLATE_LOGO_NAME,
      'type' => self::DOCUMENT_TYPE_CONSTANT,
      'folder' => null,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_SEAL => [
      'name' => self::DOCUMENT_TEMPLATE_SEAL_NAME,
      'type' => self::DOCUMENT_TYPE_CONSTANT,
      'folder' => null,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_STANDARD_LETTER => [
      'name' => self::DOCUMENT_TEMPLATE_STANDARD_LETTER_NAME,
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
      'folder' => null,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_PROJECT_DEBIT_NOTE_MANDATE => [
      'name' => self::DOCUMENT_TEMPLATE_PROJECT_DEBIT_NOTE_MANDATE_NAME,
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
      'folder' => self::FINANCE_FOLDER,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_GENERAL_DEBIT_NOTE_MANDATE => [
      'name' => self::DOCUMENT_TEMPLATE_GENERAL_DEBIT_NOTE_MANDATE_NAME,
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
      'folder' => self::FINANCE_FOLDER,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_MEMBER_DATA_UPDATE => [
      'name' => self::DOCUMENT_TEMPLATE_MEMBER_DATA_UPDATE_NAME,
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
      'folder' => self::FINANCE_FOLDER,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_INVOICE => [
      'name' => self::DOCUMENT_TEMPLATE_INVOICE_NAME,
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
      'folder' => self::FINANCE_FOLDER,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_STANDARD_RECEIPT => [
      'name' => self::DOCUMENT_TEMPLATE_STANDARD_RECEIPT_NAME,
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
      'folder' => self::FINANCE_FOLDER,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_DONATION_RECEIPT => [
      'name' => self::DOCUMENT_TEMPLATE_DONATION_RECEIPT_NAME,
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
      'folder' => self::FINANCE_FOLDER,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_INSTRUMENT_INSURANCE_RECORD => [
      'name' => self::DOCUMENT_TEMPLATE_INSTRUMENT_INSURANCE_RECORD_NAME,
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
      'folder' => self::FINANCE_FOLDER,
      'blank' => false,
    ],
  ];

  /** @return void */
  protected static function documentsTemplatesTranslationHack():void
  {
    self::t(self::DOCUMENT_TEMPLATE_LOGO_NAME);
    self::t(self::DOCUMENT_TEMPLATE_SEAL_NAME);
    self::t(self::DOCUMENT_TEMPLATE_STANDARD_LETTER_NAME);
    self::t(self::DOCUMENT_TEMPLATE_PROJECT_DEBIT_NOTE_MANDATE_NAME);
    self::t(self::DOCUMENT_TEMPLATE_GENERAL_DEBIT_NOTE_MANDATE_NAME);
    self::t(self::DOCUMENT_TEMPLATE_MEMBER_DATA_UPDATE_NAME);
    self::t(self::DOCUMENT_TEMPLATE_INVOICE_NAME);
    self::t(self::DOCUMENT_TEMPLATE_STANDARD_RECEIPT_NAME);
    self::t(self::DOCUMENT_TEMPLATE_DONATION_RECEIPT_NAME);
    self::t(self::DOCUMENT_TEMPLATE_INSTRUMENT_INSURANCE_RECORD_NAME);
  }

  /**
   * @var string
   * Name of a participant field holding a personal signature. This is used by
   * the OrganizationalRolesService in order to find images of signatures of
   * the organizing committee.
   */
  public const SIGNATURE_FIELD_NAME = 'signature';

  /**
   * @var int
   * Default auto-save interval in seconds. Used by the email-form
   */
  public const DEFAULT_AUTOSAVE_INTERVAL = 300;

  /** @var array Config-keys for the mailing-list server REST access */
  public const MAILING_LIST_REST_CONFIG = [
    'url' => 'mailingListRestUrl',
    'user' => 'mailingListRestUser',
    'password' => 'mailingListRestPassword',
  ];
  /** @var array Config-keys for some general mailing list settings */
  public const MAILING_LIST_CONFIG = [
    'domain' => 'mailingListEmailDomain',
    'web' => 'mailingListWebPages',
    'owner' => 'mailingListDefaultOwner',
    'moderator' => 'mailingListDefaultModerator',
  ];
  /** @var string Config-key for the announcements mailing list */
  public const ANNOUNCEMENTS_MAILING_LIST_KEY = 'announcementsMailingList';
  /** @var string Config-key for the announcements mailing list */
  public const ANNOUNCEMENTS_MAILING_LIST_DISPLAY_NAME_KEY = 'announcementsMailingListName';

  public const SMTP_SERVER = 'smtpserver';
  public const IMAP_SERVER = 'imapserver';
  public const SMTP_PORT = 'smtpport';
  public const IMAP_PORT = 'imapport';
  public const SMTP_SECURITY = 'smtpsecurity';
  public const IMAP_SECURITY = 'imapsecurity';
  public const EMAIL_TEST_MODE = 'emailtestmode';

  /** @var string Config-key for the global email account user-id */
  public const EMAIL_USER = 'emailUser';

  /** @var string Config-key for the global email account password */
  public const EMAIL_PASSWORD = 'emailpassword';

  /** @var string Config-key for bulk-email message composition. */
  public const BULK_EMAIL_SUBJECT_TAG = 'bulkEmailSubjectTag';

  /** @var string Config-key for pre-send validation. */
  public const PRE_SEND_VALIDATION_EXTERNAL_LINKS_SSL_VERIFY = 'preSendValidationExternalLinksSSLVerify';

  /** @var string Config-key for pre-send validation. */
  public const PRE_SEND_VALIDATION_EXTERNAL_LINKS_ENFORCE_HTTPS = 'preSendValidationExternalLinksEnforceHttps';

  /** @var sting Config-key for attachment customization. */
  public const CLOUD_ATTACHMENT_ALWAYS_LINK = 'cloudAttachmentAlwaysLink';

  /** @var sting Config-key for attachment customization. */
  public const ATTACHMENT_LINK_EXPIRATION_LIMIT = 'attachmentLinkExpirationLimit';

  /** @var sting Config-key for attachment customization. */
  public const ATTACHMENT_LINK_SIZE_LIMIT = 'attachmentLinkSizeLimit';

  /** @var string Config-key for the bulk email privacy notice */
  public const BULK_EMAIL_PRIVACY_NOTICE = 'bulkEmailPrivacyNotice';

  /** @var string */
  public const USER_GROUP_KEY = 'userGroup';

  /** @var string */
  public const USER_AND_GROUP_BACKEND_KEY = 'userAndGroupBackend';

  /** @var string */
  public const ADMIN_GROUP_SUFFIX = '-admin';

  /** @var string */
  public const CONFIG_LOCK_KEY = 'configlock';

  /** @var string */
  public const EMAIL_FROM_NAME_KEY = 'emailfromname';

  /** @var string */
  public const EMAIL_FROM_DOMAIN_KEY = 'emailFromDomain';

  /** @var string */
  public const EMAIL_FROM_ADDRESS_KEY = 'emailfromaddress';

  /** @var string */
  public const EMAIL_TEST_NAME_KEY = 'emailtestname';

  /** @var string */
  public const EMAIL_TEST_ADDRESS_KEY = 'emailtestaddress';

  /** @var string */
  public const EXECUTIVE_BOARD_PROJECT_KEY = 'executiveBoardProject';

  /** @var string */
  public const EXECUTIVE_BOARD_PROJECT_ID_KEY = self::EXECUTIVE_BOARD_PROJECT_KEY . 'Id';

  /** @var string */
  public const CLUB_MEMBERS_PROJECT_KEY = 'memberProject';

  /** @var string */
  public const CLUB_MEMBER_PROJECT_ID_KEY = self::CLUB_MEMBERS_PROJECT_KEY . 'Id';

  /** @var string */
  public const WIKI_NAME_SPACE_KEY = 'wikinamespace';

  /** @var string */
  public const ORCHESTRA_NAME_KEY = 'orchestra';

  /** @var string */
  public const APP_ENCRYPTION_KEY_HASH_KEY = 'encryptionKeyHash';

  /** @var string */
  public const CSP_FAILURE_TOKEN_KEY = 'cspfailuretoken';

  /** @var string */
  public const ORCHESTRA_LOCALE_KEY = 'orchestraLocale';

  /** @var string */
  public const APP_DB_NAME = 'dbname';

  /** @var string */
  public const APP_DB_USER = 'dbuser';

  /** @var string */
  public const APP_DB_PASSWORD = 'dbpassword';

  /** @var string */
  public const APP_DB_SERVER = 'dbserver';

  public const APP_DB_KEYS = [
    self::APP_DB_NAME,
    self::APP_DB_PASSWORD,
    self::APP_DB_SERVER,
    self::APP_DB_USER,
  ];
}
