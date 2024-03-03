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

use DoesNotExistException;

use Psr\Log\LoggerInterface as ILogger;

use Horde_Imap_Client_Socket;
use Horde_Imap_Client;
use Horde_Imap_Client_Search_Query;
use Horde_Imap_Client_Fetch_Query;
use Horde_Imap_Client_Data_Fetch;
use Horde_Mime_Headers;
use Horde_Imap_Client_Data_Envelope;
use Horde_Mime_Part;
use Horde_Imap_Client_Ids;

/**
 * A wrapper around a given IMAP library in order to hide which one actually
 * is used. This is quite simplistic and only supports the things needed by
 * the email form, i.e. copying messages, searching for a given message-id and
 * retrieving messages in order to keep the SentEmails table consistent with
 * the IMAP server
 */
class IMAPService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  private Horde_Imap_Client_Socket $client;

  /**
   * @param ILogger $logger
   *
   * @param ConfigService $configService
   */
  public function __construct(
    ILogger $logger,
    ConfigService $configService,
  ) {
    $this->logger = $logger;
    $this->configService = $configService;
    $this->l = $this->l10n();
  }

  /**
   * @return void
   */
  public function connect():void
  {
    $imapHost   = $this->getConfigValue('imapserver');
    $imapPort   = $this->getConfigValue('imapport');
    $imapSecurity = $this->getConfigValue('imapsecurity');
    $user = $this->getConfigValue('emailuser');
    $pass = $this->getConfigValue('emailpassword');

    switch ($imapSecurity) {
      case 'starttls':
        $imapSecurity = 'tls';
        break;
      case 'ssl':
        break;
      default:
        $imapSecurity = false;
        break;
    }

    $params = [
      'username' => $user,
      'password' => $pass,
      'hostspec' => $imapHost,
      'port' => $imapPort,
      'secure' => 'tls',
      'timeout' => 20,
      'context' => [
        'ssl' => [
          'verify_peer' => true,
          'verify_peer_name' => true,
        ],
      ],
    ];
    $this->client = new Horde_Imap_Client_Socket($params);
    $this->client->login();
  }

  /**
   * @return void
   */
  public function disconnet():void
  {
    if (!empty($this->client)) {
      $this->client->logout();
    }
  }

  /**
   * @param Horde_Imap_Client_Data_Envelope $envelope
   *
   * @return string
   */
  protected function decodeSubject(Horde_Imap_Client_Data_Envelope $envelope):string
  {
    // Try a soft conversion first (some installations, eg: Alpine linux,
    // have issues with the '//IGNORE' option)
    $subject = $envelope->subject;
    $utf8 = iconv('UTF-8', 'UTF-8', $subject);
    if ($utf8 !== false) {
      return $utf8;
    }
    return iconv("UTF-8", "UTF-8//IGNORE", $subject);
  }

}
