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

use OCA\CAFEVDB\Settings\ConfigConstants;

/**
 * Simple setting as enum.
 */
#[TSAttributes\TypeScript]
enum EnumSimpleSettingsKey: string
{
  use \OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait;

  case IMPORT_CLUB_MEMBERS_AS_CLOUD_USERS = 'importClubMembersAsCloudUsers';
  case CLOUD_USER_VIEWS_DATABASE = 'cloudUserViewsDatabase';
  case MUSICIAN_PERSONALIZED_VIEWS = 'musicianPersonalizedViews';
  //
  case SMTP_SERVER = ConfigConstants::SMTP_SERVER;
  case IMAP_SERVER = ConfigConstants::IMAP_SERVER;
  case SMTP_PORT = ConfigConstants::SMTP_PORT;
  case IMAP_PORT = ConfigConstants::IMAP_PORT;
  case SMTP_SECURITY = ConfigConstants::SMTP_SECURITY;
  case IMAP_SECURITY = ConfigConstants::IMAP_SECURITY;
  case EMAIL_TEST_MODE = ConfigConstants::EMAIL_TEST_MODE;
  //
  case ANNOUNCEMENTS_MAILING_LIST_DISPLAY_NAME_KEY = ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_DISPLAY_NAME_KEY;
  case BULK_EMAIL_SUBJECT_TAG = ConfigConstants::BULK_EMAIL_SUBJECT_TAG;
  case EMAIL_USER = ConfigConstants::EMAIL_USER;
  case EMAIL_PASSWORD = ConfigConstants::EMAIL_PASSWORD;
  case EMAIL_FROM_NAME_KEY = ConfigConstants::EMAIL_FROM_NAME_KEY;
  case EMAIL_TEST_NAME_KEY = ConfigConstants::EMAIL_TEST_NAME_KEY;
  case EMAIL_FROM_DOMAIN_KEY = ConfigConstants::EMAIL_FROM_DOMAIN_KEY;
  case BULK_EMAIL_PRIVACY_NOTICE = ConfigConstants::BULK_EMAIL_PRIVACY_NOTICE;
  case ANNOUNCEMENTS_MAILING_LIST_KEY = ConfigConstants::ANNOUNCEMENTS_MAILING_LIST_KEY;
  case EMAIL_TEST_ADDRESS_KEY = ConfigConstants::EMAIL_TEST_ADDRESS_KEY;
  case EMAIL_FROM_ADDRESS_KEY = ConfigConstants::EMAIL_FROM_ADDRESS_KEY;
  case ATTACHMENT_LINK_EXPIRATION_LIMIT = ConfigConstants::ATTACHMENT_LINK_EXPIRATION_LIMIT;
  case ATTACHMENT_LINK_SIZE_LIMIT = ConfigConstants::ATTACHMENT_LINK_SIZE_LIMIT;
  //
  case MAILING_LIST_REST_URL = ConfigConstants::MAILING_LIST_REST_CONFIG['url'];
  case MAILING_LIST_REST_USER = ConfigConstants::MAILING_LIST_REST_CONFIG['user'];
  case MAILING_LIST_REST_PASSWORD = ConfigConstants::MAILING_LIST_REST_CONFIG['password'];
  //
  case MAILING_LIST_EMAIL_DOMAIN = ConfigConstants::MAILING_LIST_CONFIG['domain'];
  case MAILING_LIST_WEB_PAGES = ConfigConstants::MAILING_LIST_CONFIG['web'];
  case MAILING_LIST_DEFAULT_OWNER = ConfigConstants::MAILING_LIST_CONFIG['owner'];
  case MAILING_LIST_DEFAULT_MODERATOR = ConfigConstants::MAILING_LIST_CONFIG['moderator'];
  //
  case BANK_ACCOUNT_BANK_HOLIDAYS = ConfigConstants::BANK_ACCOUNT_BANK_HOLIDAYS;
  // Special, roles, perhaps obsolete
  case PRESIDENT_USER_ID = 'presidentUserId';
  case SECRETARY_USER_ID = 'secretaryUserId';
  case TREASURER_USER_ID = 'treasurerUserId';
  case PRESIDENT_ID = 'presidentId';
  case SECRETARY_ID = 'secretaryId';
  case TREASURER_ID = 'treasurerId';
  case PRESIDENT_GROUP_ID = 'presidentGroupId';
  case SECRETARY_GROUP_ID = 'secretaryGroupId';
  case TREASURER_GROUP_ID = 'treasurerGroupId';
  case PRESIDENT_EMAIL = 'presidentEmail';
  case SECRETARY_EMAIL = 'secretaryEmail';
  case TREASURER_EMAIL = 'treasurerEmail';
}
