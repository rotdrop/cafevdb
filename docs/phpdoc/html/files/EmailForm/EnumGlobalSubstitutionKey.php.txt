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
enum EnumGlobalSubstitutionKey: string
{
  use \OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait;

  case ADDRESS = 'ADDRESS';
  case BANK_ACCOUNT = 'BANK_ACCOUNT';
  case BANK_TRANSACTION_DUE_DATE = 'BANK_TRANSACTION_DUE_DATE';
  case BANK_TRANSACTION_DUE_DAYS = 'BANK_TRANSACTION_DUE_DAYS';
  case BANK_TRANSACTION_SUBMIT_DATE = 'BANK_TRANSACTION_SUBMIT_DATE';
  case BANK_TRANSACTION_SUBMIT_DAYS = 'BANK_TRANSACTION_SUBMIT_DAYS';
  case CREDITOR_IDENTIFIER = 'CREDITOR_IDENTIFIER';
  case DATE = 'DATE';
  case DATETIME = 'DATETIME';
  case ORGANIZER = 'ORGANIZER';
  case POST_PROJECT_MEDIA_FOLDER = 'POST_PROJECT_MEDIA_FOLDER';
  case POST_PROJECT_MEDIA_SHARE = 'POST_PROJECT_MEDIA_SHARE';
  case PRESIDENT = 'PRESIDENT';
  case PROJECT = 'PROJECT';
  case PROJECT_MUSIC_SHEETS_SHARE = 'PROJECT_MUSIC_SHEETS_SHARE';
  case PROJECT_MUSIC_SHEETS_SHARE_EXPIRATION = 'PROJECT_MUSIC_SHEETS_SHARE_EXPIRATION';
  case SECRETARY = 'SECRETARY';
  case SENDER = 'SENDER';
  case TIME = 'TIME';
  case TREASURER = 'TREASURER';

  /** @return EnumSubstitutionNamespace */
  public static function namespace(): EnumSubstitutionNamespace
  {
    return EnumSubstitutionNamespace::GLOBAL;
  }
}
