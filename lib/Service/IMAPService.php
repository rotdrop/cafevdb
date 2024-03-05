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
use Throwable;

use Psr\Log\LoggerInterface as ILogger;

use Horde_Imap_Client_Exception;
use Horde_Imap_Client_Socket;
use Horde_Imap_Client;
use Horde_Imap_Client_Data_Fetch;
use Horde_Imap_Client_Data_Envelope;
use Horde_Imap_Client_Fetch_Query;
use Horde_Imap_Client_Ids;
use Horde_Imap_Client_Search_Query;
use Horde_Mime_Headers;
use Horde_Mime_Part;

/** Maybe clone the stuff, but for now we just (ab-)use it */
use OCA\CAFEVDB\Service\IMAP\ImapMessageFetcher;
use OCA\CAFEVDB\Service\IMAP\IMAPMessage;

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

  public const CONFIG_KEYS = [
    'host' => 'imapserver',
    'port' => 'imapport',
    'security' => 'imapsecurity',
    'user' => 'emailuser',
    'password' => 'emailpassword',
  ];

  public const SPECIAL_USE_ALL = Horde_Imap_Client::SPECIALUSE_ALL;
  public const SPECIAL_USE_ARCHIVE = Horde_Imap_Client::SPECIALUSE_ARCHIVE;
  public const SPECIAL_USE_DRAFTS = Horde_Imap_Client::SPECIALUSE_DRAFTS;
  public const SPECIAL_USE_FLAGGED = Horde_Imap_Client::SPECIALUSE_FLAGGED;
  public const SPECIAL_USE_JUNK = Horde_Imap_Client::SPECIALUSE_JUNK;
  public const SPECIAL_USE_SENT = Horde_Imap_Client::SPECIALUSE_SENT;
  public const SPECIAL_USE_TRASH = Horde_Imap_Client::SPECIALUSE_TRASH;

  public const SPECIAL_USE_ATTRIBUTES = [
    self::SPECIAL_USE_ALL,
    self::SPECIAL_USE_ARCHIVE,
    self::SPECIAL_USE_DRAFTS,
    self::SPECIAL_USE_FLAGGED,
    self::SPECIAL_USE_JUNK,
    self::SPECIAL_USE_SENT,
    self::SPECIAL_USE_TRASH,
  ];

  private null|Horde_Imap_Client_Socket $client = null;

  /**
   * @var null|array
   *
   * Per-request cache of the mailboxes of the email account of the orchestra.
   */
  private null|array $mailboxes = null;

  /**
   * @var null|array
   *
   * Per request cache of special use mailboxes.
   */
  private null|array $specialUseMailboxes = null;

  private string $imapUser;

  private string $imapHost;

  private int $imapPort;

  private string $imapSecurity;

  private string $imapPassword;

  /**
   * @param ILogger $logger
   *
   * @param ConfigService $configService
   *
   */
  public function __construct(
    ILogger $logger,
    ConfigService $configService,
  ) {
    $this->logger = $logger;
    $this->configService = $configService;
    $this->l = $this->l10n();

    $this->setAccount();
  }

  /**
   * Override imap connection parameters. Only non-null parameters are
   * replaced. The primary use of this function is during development in order
   * to switch to another email server than configured in the user
   * preferences.
   *
   * @param null|string $imapHost
   *
   * @param null|string $imapPort
   *
   * @param null|string $imapSecurity
   *
   * @param null|string $imapUser
   *
   * @param null|string $imapPassword
   *
   * @return IMAPService
   */
  public function setAccount(
    ?string $host = null,
    ?string $port = null,
    ?string $security = null,
    ?string $user = null,
    ?string $password = null,
  ):IMAPService {
    foreach (self::CONFIG_KEYS as $topic => $configKey) {
      $key = 'imap' . ucfirst($topic);
      $value = ${$topic} ?? null;
      if ($value !== null) {
        if ($topic == 'user') {
          $value = str_replace('%40', '@', $value);
        }
        $this->$key = $value;
        $value = $topic == 'password' ? $value[0] : $value;
        // $this->logInfo('Setting ' . $key . ' -> ' . $value);
      } else {
        $configValue = $this->getConfigValue($configKey);
        if ($configValue !== null) {
          $this->$key = $configValue;
        }
      }
    }

    return $this;
  }

  /**
   * @return void
   */
  public function connect():void
  {
    if (!empty($this->client)) {
      return;
    }
    $imapSecurity = $this->imapSecurity;
    switch ($imapSecurity) {
      case 'starttls':
        $imapSecurity = 'tls';
        break;
      case 'tls':
        break;
      case 'ssl':
        break;
      default:
        $imapSecurity = false;
        break;
    }

    // $this->logInfo('IMAP ACCOUNT ' . $imapSecurity . '://' . urlencode($this->imapUser) . ':' . $this->imapPassword[0] . 'XXX' . ':' . $this->imapHost);

    $params = [
      'username' => $this->imapUser,
      'password' => $this->imapPassword,
      'hostspec' => $this->imapHost,
      'port' => $this->imapPort,
      'secure' => $imapSecurity,
      'timeout' => 20,
      'context' => [
        'ssl' => [
          'verify_peer' => true,
          'verify_peer_name' => true,
        ],
      ],
    ];
    $this->client = new Horde_Imap_Client_Socket($params);
    try {
      $this->client->login();
    } catch (Horde_Imap_Client_Exception $e) {
      $this->logError('LOGIN FAILED ' . $imapSecurity . '://' . urlencode($this->imapUser) . ':' . $this->imapPassword[0] . 'XXX' . ':' . $this->imapHost);
      throw $e;
    }
  }

  /**
   * @return void
   */
  public function disconnet():void
  {
    if (!empty($this->client)) {
      $this->client->logout();
      $this->client = null;
    }
  }

  /**
   * @param Horde_Imap_Client_Data_Envelope $envelope
   *
   * @return string
   */
  protected static function decodeSubject(Horde_Imap_Client_Data_Envelope $envelope):string
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

  /**
   * Fetch all mailboxes and cache them for the current request.
   *
   * @return array
   */
  private function fetchMailboxes():array
  {
    if (empty($this->mailboxes)) {
      $this->connect();
      $mailboxes = $this->client->listMailboxes('*', Horde_Imap_Client::MBOX_ALL, [
        'delimiter' => true,
        'attributes' => true,
        'special_use' => true,
      ]);
      $mailboxes = array_filter(
        $mailboxes,
        fn(array $folder) => !in_array('\\nexistent', $folder['attributes']),
      );

      $mboxNames = array_map(fn(array $mailbox) => $mailbox['mailbox']->utf8, $mailboxes);
      $this->mailboxes = array_combine($mboxNames, $mailboxes);

      foreach ($mailboxes as $folderName => $folder) {
        $attributes = array_map('strtolower', $folder['attributes']);
        foreach (self::SPECIAL_USE_ATTRIBUTES as $attribute) {
          if (in_array(strtolower($attribute), $attributes)) {
            $this->specialUseMailboxes[$attribute] = $folderName;
          }
        }
      }
    }
    return $this->mailboxes;
  }

  /**
   * Append the given message to the given $mailbox. $mailbox may be one of
   * the special-use mailboxes like IMAPService::SPECIAL_USE_SENT.
   *
   * @param string $mimeMessage The message to append.
   *
   * @param string $mailbox The mailbox to append the message to.
   *
   * @return void
   */
  public function append(string $mimeMessage, string $mailbox):void
  {
    $this->connect();
    if (in_array($mailbox, self::SPECIAL_USE_ATTRIBUTES)) {
      $folder = $this->specialUseMailboxes[$mailbox] ?? null;
      if (empty($folder)) {
        throw new DoesNotExistException($this->l->t('Special-use mailbox "%1$s" does not seem to exist on the server.', $mailbox));
      }
      $this->client->append(
        $folder['mailbox'],
        [
          'data' => $mimeMessage,
          'flags' => Horde_Imap_Client::FLAG_SEEN|Horde_Imap_Client::FLAG_RECENT,
        ],
      );
    }
  }

  /**
   * @param string $attribute Special use attribute like like IMAPService::SPECIAL_USE_SENT.
   *
   * @return null|array
   */
  public function getSpecialUseMailbox(string $attribute):null|array
  {
    $this->fetchMailboxes();
    return $this->specialUseMailboxes[$attribute] ?? null;
  }

  /**
   * Search all folders for messages with the given message id.
   *
   * @param string|array $messageId
   *
   * @param null|bool $useFirst Defaults to \true. If false return an array with
   * all messages with the given id, althoung in principle all messages with
   * the same id should be identical.
   *
   * @return array|IMAPMessage
   */
  public function searchMessageId(
    string|array $messageId,
    null|bool $useFirst = null,
  ):array|IMAPMessage {
    if ($useFirst === null) {
      $useFirst = !is_array($messageId);
    }
    return $this->searchHeaders(or: [ 'message-id' => $messageId, ], useFirst: $useFirst);
  }

  /**
   * Search for the given headers.
   *
   * @param array $and HEADER => VALUE pairs which are combined in an and-query.
   *
   * @param array $or HEADER => VALUE pairs which are combined in an or-query.
   *
   * @param null|string $mbox Restrict the search to the given mailbox. If \null search all mailboxes.
   *
   * @param bool $fuzzy See RFC 6203.
   *
   * @param bool $useFirst Stop at the first match.
   *
   * @return array|IMAPMessage The array of found matches.
   *
   * @SuppressWarnings(PHPMD.ShortVariable)
   */
  public function searchHeaders(
    array $and = [],
    array $or = [],
    ?string $mbox = null,
    bool $fuzzy = false,
    bool $useFirst = false,
  ):array|IMAPMessage {
    $mailboxes = $this->fetchMailboxes();
    if ($mbox !== null) {
      $mailboxes = array_filter([ $mbox => $mailboxes[$mbox] ?? null ]);
    }
    $andQueries = [];
    foreach ($and as $header => $text) {
      if (is_numeric($header) && is_array($text)) {
        // [ [ HEADER1 => VALUE1 ], [ HEADER2 => VALUE2 ], ... ]
        $header = array_key_first($text);
        $text = $text[$header];
      } elseif (is_array($text)) {
        $values = $text;
        foreach ($values as $text) {
          $andQueries[] = $query = new Horde_Imap_Client_Search_Query();
          $query->headerText($header, $text);
        }
        continue;
      }
      $andQueries[] = $query = new Horde_Imap_Client_Search_Query();
      $query->headerText($header, $text);
    }
    $orQueries = [];
    foreach ($or as $header => $text) {
      if (is_numeric($header) && is_array($text)) {
        // [ [ HEADER1 => VALUE1 ], [ HEADER2 => VALUE2 ], ... ]
        $header = array_key_first($text);
        $text = $text[$header];
      } elseif (is_array($text)) {
        $values = $text;
        foreach ($values as $text) {
          $orQueries[] = $query = new Horde_Imap_Client_Search_Query();
          $query->headerText($header, $text);
        }
        continue;
      }
      $orQueries[] = $query = new Horde_Imap_Client_Search_Query();
      $query->headerText($header, $text);
    }
    if (!empty($andQueries) && count($andQueries) > 1) {
      $andQuery = $query = new Horde_Imap_Client_Search_Query();
      $query->andSearch($andQueries);
      // $this->logInfo('DOING AND QUERIES ' . print_r($query, true));
    }
    if (!empty($orQueries) && count($orQueries) > 1) {
      $orQuery = $query = new Horde_Imap_Client_Search_Query();
      $query->orSearch($orQueries);
      // $this->logInfo('DOING OR QUERIES ' . print_r($query, true));
    }
    if (!empty($orQueries) && !empty($andQueries)) {
      $query = new Horde_Imap_Client_Search_Query();
      $query->andSearch([ $andQuery, $orQuery ]);
    }
    $results = [];
    foreach ($mailboxes as $folderName => $folder) {
      $mbox = $folder['mailbox'];
      try {
        $searchResults = $this->client->search($mbox, $query);
      } catch (Throwable $t) {
        // don't care
        continue;
      }
      if ($searchResults['count'] == 0) {
        continue;
      }
      // $this->logInfo('FOUND MATCHES ' . $searchResults['count']);
      $ids = $searchResults['match'];
      $query = new Horde_Imap_Client_Fetch_Query();
      $query->envelope();
      $query->flags();
      $query->uid();
      $query->imapDate();
      $query->headerText([
        'cache' => true,
        'peek' => true,
      ]);
      $fetchResults = $this->client->fetch($mbox, $query, [ 'ids' => $ids ]);

      $results = array_merge(
        $results,
        array_map(
          fn(Horde_Imap_Client_Data_Fetch $fetchResult)
            => (new ImapMessageFetcher(
              $fetchResult->getUid(),
              $folderName,
              $this->client,
            ))
            ->withBody(true)
            ->fetchMessage($fetchResult),
          iterator_to_array($fetchResults),
        ),
      );
      if ($useFirst) {
        return reset($results);
      }
    }
    return $results;
  }
}
