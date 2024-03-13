<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Wrapped\Horde_Mail_Exception;
use OCA\CAFEVDB\Wrapped\Horde_Mail_Rfc822;
use OCA\CAFEVDB\Wrapped\Horde_Mail_Rfc822_List;
use OCA\CAFEVDB\Wrapped\Horde_Mail_Rfc822_Address;

use OCA\CAFEVDB\Common\PHPMailer;
use OCA\CAFEVDB\Exceptions;

/**
 * Parsing and validation of email addresses according to RFC822 using. Mostly
 * a wrapper around support classes like Horder and PHPMailer.
 */
class EmailAddressService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /**
   * @param ILogger $logger
   *
   * @param IL10N $l
   *
   * @param PHPMailer $phpMailer
   */
  public function __construct(
    protected ILogger $logger,
    protected IL10N $l,
    private PHPMailer $phpMailer,
  ) {
  }

  /**
   * Parse the given address string and return an array of email address
   * without any comments or clear-text names, just the bare email addresses.
   *
   * @param string $addressString
   *
   * @return array<string, string>
   * ```
   * [ FOO@BAR.COM => DISPLAY_NAME, ... ]
   * ```
   *
   * @throws Exceptions\EnduserNotificationException
   */
  public function parseAddressString(string $addressString):array
  {
    $parser = new Horde_Mail_Rfc822;
    try {
      $parsedEmail = $parser->parseAddressList($addressString);
    } catch (Horde_Mail_Exception $e) {
      throw new Exceptions\EnduserNotificationException($this->l->t('Horde failed to parse address "%s".', $addressString), 0, $e);
    }

    $emailArray = [];
    /** @var Horde_Mail_Rfc822_Address $emailRecord */
    foreach ($parsedEmail as $emailRecord) {
      if (!($emailRecord instanceof Horde_Mail_Rfc822_Address)) {
        // don't care about group stuff
        continue;
      }
      if ($emailRecord->host === 'localhost') {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('Missing host for email-address: %s. ', $emailRecord->mailbox)
        );
      }
      $recipient = strtolower($emailRecord->mailbox . '@' . $emailRecord->host);
      if (!$this->phpMailer->validateAddress($recipient)) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('Validation failed for: %s. ', $recipient)
        );
      }
      $displayName = $emailRecord->personal ?: ($emailRecord->comment ?? null);
      if (is_array(($displayName))) {
        $displayName = implode(', ', $displayName);
      }
      $emailArray[$recipient] = $displayName;
    }
    return $emailArray;
  }
}
