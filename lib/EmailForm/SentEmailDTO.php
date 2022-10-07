<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**
 * Data-transfer object in order to later generate the log-entry in
 * the data-base from it.
 */
class SentEmailDTO
{
  /**
   * @var array<int>
   * ```
   * [ [ 'name' => NAME, 'email' => EMAIL ], ... ]
   * ```
   */
  public $recipients;

  /**
   * @var string
   * Message body without attachments
   */
  public $message;

  /**
   * @var string
   * Message subject
   */
  public $subject;

  /**
   * @var string
   * Carbon-copy recipients as one string.
   */
  public $CC;

  /**
   * @var string
   * Blind-carbon-copy recipients as one string.
   */
  public $BCC;
}
