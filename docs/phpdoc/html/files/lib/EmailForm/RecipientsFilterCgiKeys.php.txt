<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020-2026 Claus-Justus Heine
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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/** All the names used by the legacy email composer form template. */
#[TSAttributes\TypeScript]
class RecipientsFilterCgiKeys
{
  public const ANNOUNCEMENTS_MAILING_LIST = RecipientsFilter::ANNOUNCEMENTS_MAILING_LIST_KEY;
  public const BASIC_RECIPIENTS_SET = RecipientsFilter::BASIC_RECIPIENTS_SET_KEY;
  public const EXCEPT_PROJECT = RecipientsFilter::EXCEPT_PROJECT_KEY;
  public const FROM_PROJECT_CONFIRMED = RecipientsFilter::FROM_PROJECT_CONFIRMED_KEY;
  public const FROM_PROJECT_PRELIMINARY = RecipientsFilter::FROM_PROJECT_PRELIMINARY_KEY;
  public const PROJECT_MAILING_LIST = RecipientsFilter::PROJECT_MAILING_LIST_KEY;
  //
  public const APPLY_INSTRUMENTS_FILTER = 'applyInstrumentsFilter';
  public const INSTRUMENTS_FILTER = 'instrumentsFilter';
  public const PARTICIPATION_STATUS_FILTER = 'participationStatusFilter';
  public const REDO_INSTRUMENTS_FILTER = 'redoInstrumentsFilter';
  public const RESET_INSTRUMENTS_FILTER = 'resetInstrumentsFilter';
  public const SELECTED_RECIPIENTS = 'selectedRecipients';
  public const UNDO_INSTRUMENTS_FILTER = 'undoInstrumentsFilter';
  //
  /**
   * @var string
   *
   * Not an input-name, but used as cgi-key to trigger a history snapshot.
   */
  public const HISTORY_SNAPSHOT = RecipientsFilter::HISTORY_SNAPSHOT_KEY;
  /**
   * @var string
   *
   * Not an input-name, but used as cgi-key to indicate whether the form had
   * user interaction.
   */
  public const FORM_STATUS = 'formStatus';
  /**
   * @var string
   *
   * Not an input-name, but used as cgi-key to indicate whether the set of
   * recipients may be modified by the user.
   */
  public const FROZEN_RECIPIENTS = 'frozenRecipients';
}
