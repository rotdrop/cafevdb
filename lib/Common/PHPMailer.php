<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Common;

use \PHPMailer\PHPMailer\PHPMailer as PHPMailerUpstream;

/**
 * Slightly enhanced version of PHPMailer
 *
 * - return the message headers without body
 */
class PHPMailer extends PHPMailerUpstream
{
  /**
   * @var int Index of data-field into the items returned by
   * self::getAttachments().
   */
  public const ATTACHMENT_INDEX_DATA = 0;
  /**
   * @var int Index of name field into the items returned by
   * self::getAttachments().
   */
  public const ATTACHMENT_INDEX_NAME = 2;
  /**
   * @var int Index of encoding field into the items returned by
   * self::getAttachments().
   */
  public const ATTACHMENT_INDEX_ENCODING = 3;
  /**
   * @var int Index of mime-type field into the items returned by
   * self::getAttachments().
   */
  public const ATTACHMENT_INDEX_MIME_TYPE = 4;

  protected const DEBUG_PREFIX = 'CLIENT -> SERVER: ';
  protected const DEBUG_DATA = 'DATA';
  protected const DEBUG_QUIT = 'QUIT';

  protected $mimeMessageTotalSize = 0;
  protected $mimeDataSent;

  protected ?Closure $progressCallback = null;

  /**
   * @var array
   *
   * References message ids.
   */
  protected $references = [];

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
    $headers = $this->MIMEHeader . $this->mailHeader;
    if (count($this->bcc) > 0) {
      $headers .= $this->addrAppend('Bcc', $this->bcc);
    }
    return static::stripTrailingWSP($headers);
  }

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(?bool $exceptions = null)
  {
    parent::__construct($exceptions);
    $this->Debugoutput = function($str, $lvl) {
      $tag = substr($str, strlen(self::DEBUG_PREFIX), 4);

      if ($tag == self::DEBUG_DATA) {
        $this->mimeDataSent = 0;
      } elseif ($tag == self::DEBUG_QUIT) {
        // nothing
      } else {
        $this->mimeDataSent += strlen($str) - strlen(self::DEBUG_PREFIX);
        if ($this->mimeDataSent > $this->mimeMessageTotalSize) {
          $this->mimeMessageTotalSize = $this->mimeDataSent;
        }
      }

      if ($this->progressCallback !== null) {
        $this->progressCallback($this->mimeDataSent, $this->mimeMessageTotalSize);
      }
    };
    $this->SMTPDebug = 1;
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   *
   * Override the vanilla method to use a more decent html to text converter
   * by default.
   */
  public function html2text($html, $advanced = false)
  {
    if ($advanced !== false) {
      return parent::html2text($html, $advanced);
    }
    return Html2Text::convert($html);
  }

  /**
   * @param null|Closure $progressCallback
   *
   * @return void
   */
  public function setProgressCallback(?Closure $progressCallback):void
  {
    $this->progressCallback = $progressCallback;
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

  /**
   * Generate a messsage id just like it would be generated by PHPMailer.
   *
   * @return string
   */
  public function generateMessageId():string
  {
    return sprintf('<%s@%s>', $this->generateId(), $this->serverHostname());
  }

  /**
   * Add a referenced message id.
   *
   * @param string $messageId
   *
   * @return void
   */
  public function addReference(string $messageId):void
  {
    $this->references[] = $messageId;
  }

  /**
   * Set the referenced message ids.
   *
   * @param array $references
   *
   * @return void
   */
  public function setReferences(array $references):void
  {
    $this->references = $references;
  }

  /**
   * Clear the referenced message ids.
   *
   * @return void
   */
  public function clearReferences():void
  {
    $this->setReferences([]);
  }

  /**
   * Get the referenced message ids.
   *
   * @return array
   */
  public function getReferences():array
  {
    return $this->references;
  }

  /**
   * {@inheritdoc}
   *
   * Override the vanilla method as PHPMailerUpstream does not provide support
   * for the References header.
   */
  public function createHeader()
  {
    $header = parent::createHeader();
    if (!empty($this->references)) {
      $header .= $this->headerLine('References', implode(self::$LE . ' ', $this->references));
    }
    return $header;
  }

  /**
   * {@inheritdoc}
   *
   * Add also the Bcc headers in order to get a useful preview and
   * more useful sent messages.
   */
  public function getSentMIMEMessage()
  {
    return $this->getMailHeaders() . static::$LE . static::$LE . $this->MIMEBody;
  }

  /**
   * Parse a failed-recipients error string and explode into a list of failed
   * recipients. Return null if $errorMessages does not refer to failed recipients.
   *
   * @param string $errorMessage Error message, e.g. from an exception.
   *
   * @return array
   */
  public function failedRecipients(string $errorMessage):array
  {
    // throw new Exception($this->lang('recipients_failed') . $errstr, self::STOP_CONTINUE);
    $failedRecipientsL10N = $this->lang('recipients_failed');
    if (!str_starts_with($errorMessage, $failedRecipientsL10N)) {
      return null;
    }
    $failedRecipientLines = preg_split('/\r\n|\r|\n/', substr($errorMessage, strlen($failedRecipientsL10N)));
    $failedRecipients = [];
    foreach ($failedRecipientLines as $failedRecipientLine) {
      // Probably depending on the server setup, the error message may even
      // contain multiple repetitions of the email address (the first one is
      // from the phpMailer class)
      //
      // mail@michaelaneuwirth.de: <mail@michaelaneuwirth.de>
      // <mail@michaelaneuwirth.de>: Recipient address rejected: Domain not
      // found
      list($email, $rest) = explode(':', $failedRecipientLine, 2);
      trim($email);
      trim($rest);
      $errorParts = explode(':', $rest, 2);
      if (count($errorParts) > 1) {
        $smtpAddressTag = $errorParts[0];
        if (empty(trim(str_replace($email, '', $smtpAddressTag), ' <>:'))) {
          $rest = trim($errorParts[1]);
        }
      }
      $failedRecipients[$email] = $rest;
    }
    return $failedRecipients;
  }
}
