<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
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
