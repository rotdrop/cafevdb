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

namespace OCA\CAFEVDB\Common;

use \PHPMailer\PHPMailer\PHPMailer as PHPMailerUpstream;

/**
 * Slightly enhanced version of PHPMailer
 *
 * - return the message headers without body
 */
class PHPMailer extends PHPMailerUpstream
{
  protected const DEBUG_PREFIX = 'CLIENT -> SERVER: ';
  protected const DEBUG_DATA = 'DATA';
  protected const DEBUG_QUIT = 'QUIT';

  protected $mimeMessageTotalSize = 0;
  protected $mimeDataSent;

  /**
   * Returns the complete headers, but not the body.
   * Only valid post preSend().
   *
   * @see PHPMailerUpstream::preSend()
   *
   * @return string
   */
  public function getMailHeaders()
  {
    return static::stripTrailingWSP($this->MIMEHeader . $this->mailHeader);
  }

  public function __construct($exceptions = null)
  {
    parent::__construct($exceptions);
    $this->Debugoutput = function($str, $lvl) {
      $tag = substr($str, strlen(self::DEBUG_PREFIX), 4);

      if ($tag == self::DEBUG_DATA) {
        $this->mimeDataSent = 0;
      } else if ($tag == self::DEBUG_QUIT) {
        // nothing
      } else {
        $this->mimeDataSent += strlen($str) - strlen(self::DEBUG_PREFIX);
        if ($this->mimeDataSent > $this->mimeMessageTotalSize) {
          $this->mimeMessageTotalSize = $this->mimeDataSent;
        }
      }

      if (is_callable($this->progressCallback)) {
        call_user_func($this->progressCallback, $this->mimeDataSent, $this->mimeMessageTotalSize);
      }
    };
    $this->SMTPDebug = 1;
  }

  /**
   * Create a message and send it.
   * Uses the sending method specified by $Mailer.
   *
   * @throws Exception
   *
   * @return bool false on error - See the ErrorInfo property for details of the error
   */
  public function send()
  {
    try {
      if (!$this->preSend()) {
        $this->mimeMessageTotalSize = 0;
        return false;
      }
      $this->mimeMessageTotalSize = strlen($this->getMailHeaders())
        + 2 + strlen($this->MIMEBody);
      $this->mimeDataSent = 0;

      return $this->postSend();
    } catch (Exception $exc) {
      $this->mailHeader = '';
      $this->setError($exc->getMessage());
      if ($this->exceptions) {
        throw $exc;
      }

      return false;
    }
  }

}
