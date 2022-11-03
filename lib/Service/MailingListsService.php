<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use \RuntimeException;
use \InvalidArgumentException;
use GuzzleHttp\Client as RestClient;
use GuzzleHttp\Exception\ConnectException;

use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Storage\UserStorage;

/** Handle participant mailing-list services. */
class MailingListsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\FakeTranslationTrait;

  const ROLE = 'role';

  const PRE_VERIFIED = 'pre_verified';
  const PRE_CONFIRMED = 'pre_confirmed';
  const PRE_APPROVED = 'pre_approved';
  const SEND_WELCOME_MESSAGE = 'send_welcome_message';
  const SEND_GOODBYE_MESSAGE = 'send_goodbye_message';

  private const DEFAULT_SUBSCRIPTION_DATA = [
    self::PRE_VERIFIED => true,
    self::PRE_CONFIRMED => true,
    self::PRE_APPROVED => true,
    self::SEND_WELCOME_MESSAGE => true,
    self::ROLE => self::ROLE_MEMBER,
  ];

  const TEMPLATE_DIR_MAILING_LISTS = 'mailing lists';
  const TEMPLATE_DIR_AUTO_RESPONSES = 'auto responses';
  const TEMPLATE_TYPE_UNSPECIFIC = '';
  const TEMPLATE_TYPE_ANNOUNCEMENTS = 'announcements';
  const TEMPLATE_TYPE_PROJECTS = 'projects';

  const ROLE_MEMBER = 'member';
  const ROLE_MODERATOR = 'moderator';
  const ROLE_OWNER = 'owner';
  const ROLES = [
    self::ROLE_MEMBER, self::ROLE_MODERATOR, self::ROLE_OWNER,
  ];

  const SUBSCRIBER_EMAIL = 'subscriber';
  const MEMBER_DISPLAY_NAME = 'display_name';
  const MEMBER_DELIVERY_STATUS = 'delivery_status';
  const MEMBER_DELIVERY_MODE = 'delivery_mode';
  const DELIVERY_STATUS_DISABLED_BY_USER = 'by_user';
  const DELIVERY_STATUS_DISABLED_BY_MODERATOR = 'by_moderator';
  const DELIVERY_STATUS_DISABLED_BY_BOUNCES = 'by_bounces';
  const DELIVERY_STATUS_ENABLED = 'enabled';
  const DELIVERY_MODE_REGULAR = 'regular';
  const DELIVERY_MODE_PLAINTEXT_DIGESTS = 'plaintext_digests';
  const DELIVERY_MODE_MIME_DIGESTS = 'mime_digests';
  const DELIVERY_MODE_SUMMARY_DIGESTS = 'summary_digests';

  const SUBSCRIPTION_SELF_LINK = 'self_link';

  const TEMPLATE_FILE_PREFIX = 'list:';
  const TEMPLATE_FILE_ADMIN = 'admin:';
  const TEMPLATE_FILE_MEMBER = 'member:';
  const TEMPLATE_FILE_USER = 'user';
  const TEMPLATE_FILE_RCPTS = [
    self::TEMPLATE_FILE_PREFIX . self::TEMPLATE_FILE_ADMIN,
    self::TEMPLATE_FILE_PREFIX . self::TEMPLATE_FILE_MEMBER,
    self::TEMPLATE_FILE_PREFIX . self::TEMPLATE_FILE_USER,
  ];

  const STATUS_SUBSCRIBED = 'subscribed';
  const STATUS_UNSUBSCRIBED = 'unsubscribed';
  const STATUS_INVITED = 'invited';
  const STATUS_WAITING = 'waiting';

  const MODERATION_ACTION_ACCEPT = 'accept';
  const MODERATION_ACTION_REJECT = 'reject';
  const MODERATOIN_ACTOIN_DEFER = 'defer';
  const MODERATION_ACTION_DISCARD = 'discard';
  const MODERATION_ACTIONS = [
    self::MODERATION_ACTION_ACCEPT,
    self::MODERATION_ACTION_DISCARD,
    self::MODERATION_ACTION_REJECT,
    self::MODERATOIN_ACTOIN_DEFER,
  ];

  const MEMBER_MODERATION_KEY = 'moderation_action';
  const MEMBER_MODERATION_ACCEPT = self::MODERATION_ACTION_ACCEPT;
  const MEMBER_MODERATION_HOLD = 'hold';
  const MEMBER_MODERATION_REJECT = self::MODERATION_ACTION_REJECT;
  const MEMBER_MODERATION_DISCARD = self::MODERATION_ACTION_DISCARD;

  const MEMBER_MODERATIONS = [
    self::MEMBER_MODERATION_ACCEPT,
    self::MEMBER_MODERATION_HOLD,
    self::MEMBER_MODERATION_REJECT,
    self::MEMBER_MODERATION_DISCARD,
  ];

  const REQUEST_OWNER_MODERATOR = 'moderator';
  const REQUEST_OWNER_SUBSCRIBER = 'subscriber';
  const REQUEST_OWNERS = [
    self::REQUEST_OWNER_MODERATOR,
    self::REQUEST_OWNER_SUBSCRIBER,
  ];

  // some config keys to avoid typos
  const LIST_CONFIG_DISPLAY_NAME = 'display_name';
  const LIST_CONFIG_FQDN_LISTNAME = 'fqdn_listname';
  const LIST_CONFIG_ACCEPTABLE_ALIASES = 'acceptabl_aliases';

  // some list-info keys in order to avoid typos
  const LIST_INFO_DISPLAY_NAME = self::LIST_CONFIG_DISPLAY_NAME;
  const LIST_INFO_FQDN_LISTNAME = self::LIST_CONFIG_FQDN_LISTNAME;
  const LIST_INFO_ADVERTISED = 'advertised';
  const LIST_INFO_DESCRIPTION = 'description';
  const LIST_INFO_LIST_NAME = 'list_name';
  const LIST_INFO_MAIL_HOST = 'mail_host';
  const LIST_INFO_MEMBER_COUNT = 'member_count';
  /**
   * @var array
   *
   * The array keys in the result of getListInfo().
   */
  const LIST_INFO_KEYS = [
    self::LIST_INFO_DISPLAY_NAME,
    self::LIST_INFO_FQDN_LISTNAME,
    self::LIST_INFO_ADVERTISED,
    self::LIST_INFO_DESCRIPTION,
    self::LIST_INFO_LIST_NAME,
    self::LIST_INFO_MAIL_HOST,
    self::LIST_INFO_MEMBER_COUNT
  ];

  public const DEFAULT_MEMBER_SEARCH_CRITERIA = [
    self::ROLE => self::ROLE_MEMBER,
  ];

  /** @var string
   * Default rest URI
   */
  private const DEFAULT_REST_URI = 'http://localhost:8001';

  /** @var RestClient */
  private $restClient;

  /** @var array */
  private $restAuth;

  /**
   * @var array
   *
   * Cache the fqdn -> list-id mapping for the current request.
   */
  private $listIdByFqdn = [];

  /**
   * @var array
   *
   * Cache the self-link for each subscription for the current request life-time.
   */
  private $selfLinkBySubscription = [];

  /**
   * @param ConfigService $configService Global app configuration service.
   */
  public function __construct(
    ConfigService $configService
  ) {
    $this->configService = $configService;
    $this->l = $this->l10n();

    $this->restClient = new RestClient([ 'base_uri' => $this->getConfigValue(
      ConfigService::MAILING_LIST_REST_CONFIG['url'],
      self::DEFAULT_REST_URI) ]);
    $this->restAuth = [
      $this->getConfigValue(ConfigService::MAILING_LIST_REST_CONFIG['user']),
      $this->getConfigValue(ConfigService::MAILING_LIST_REST_CONFIG['password']),
    ];
  }

  /** @return bool Whether the mailing list service is configured. */
  public function isConfigured():bool
  {
    foreach (ConfigService::MAILING_LIST_REST_CONFIG as $configKey) {
      if (empty($this->getConfigValue($configKey))) {
        return false;
      }
    }
    return true;
  }

  /**
   * Obtain the server configuration, this is rather for testing in
   * order to check the basic connectivity.
   *
   * @return null|array
   */
  public function getServerConfig():?array
  {
    $response = $this->restClient->get(
      '/3.1/system/configuration', [
        'auth' => $this->restAuth,
      ]);
    return empty($response->getBody()) ? null : json_decode($response->getBody(), true);
  }

  /**
   * @param null|array $coreVersion Storage for detailed version information.
   *
   * @return array Obtain the server versions.
   */
  public function getServerVersions(?array &$coreVersion = null):?array
  {
    $response = $this->restClient->get(
      '/3.1/system/versions', [
        'auth' => $this->restAuth,
      ]);
    $result = empty($response->getBody()) ? null : json_decode($response->getBody(), true);
    if (!empty($result)) {
      if (preg_match('/([0-9]+)\.([0-9]+)\.([0-9]+)/', $result['mailman_version'], $matches)) {
        $coreVersion['major'] = $matches[1];
        $coreVersion['minor'] = $matches[2];
        $coreVersion['revision'] = $matches[3];
        $coreVersion['combined'] = self::combinedVersion($matches[1], $matches[2], $matches[3]);
      }
    }
    return $result;
  }

  /**
   * MAJOR.MINOR.REVISION.
   *
   * @param int $major Major verion.
   *
   * @param int $minor Minor version.
   *
   * @param int $revision Revision.
   *
   * @return int Combined integral version.
   */
  private static function combinedVersion(int $major, int $minor, int $revision):int
  {
    return $revision + 100 + ($minor + 100 * $major);
  }

  /**
   * Fetch the list configuration from the server. Returns null if the lis
   * does not exist.
   *
   * @param string $fqdnName The list FQDN.
   *
   * @param null|string $resource A particular resource. If null the entire
   * list config is returned.
   *
   * @return null|mixed The list configuration. If a single resource
   * is requested, then only the value of the resourfce.
   */
  public function getListConfig(string $fqdnName, ?string $resource = null)
  {
    $response = $this->restClient->get(
      '/3.1/lists/' . $fqdnName . '/config' . (empty($resource) ? '' : '/' . $resource), [
        'auth' => $this->restAuth,
      ]);
    if (empty($response->getBody())) {
      return null;
    }
    $response = json_decode($response->getBody(), true);
    if (!empty($resource)) {
      return empty($response[$resource]) ? null : $response[$resource];
    }
    return $response;
  }

  /**
   * Set the list config.
   *
   * @param string $fqdnName The list FQDN.
   *
   * @param string|array $config Configuration array or a particular
   * configuraiton key.
   *
   * @param mixed $value If $config is a string then this is the configuration
   * value.
   *
   * @return bool Execution status.
   */
  public function setListConfig(string $fqdnName, mixed $config, mixed $value = null):bool
  {
    if (!is_array($config)) {
      $config = [ $config => $value ];
    }
    // Bloody Python "wisdom"
    foreach ($config as &$value) {
      if ($value === true) {
        $value = 'True';
      } elseif ($value === false) {
        $value = 'False';
      }
    }
    /* $response = */ $this->restClient->patch(
      '/3.1/lists/' . $fqdnName . '/config', [
        'json' => $config,
        'auth' => $this->restAuth,
      ]);
    return true;
  }

  /**
   * @param string $fqdnName The list FQDN.
   *
   * @return null|array The brief list-info.
   */
  public function getListInfo(string $fqdnName):?array
  {
    try {
      $response = $this->restClient->get(
        '/3.1/lists/' . $fqdnName, [
          'auth' => $this->restAuth,
        ]);
    } catch (\GuzzleHttp\Exception\ClientException $e) {
      if ($e->getResponse()->getStatusCode() == 404) {
        return null;
      }
      throw $e;
    }
    return empty($response->getBody()) ? null : json_decode($response->getBody(), true);
  }

  /**
   * Create a non-existing list.
   *
   * @param string $fqdnName The list FQDN.
   *
   * @param string $style The list-style.
   *
   * @return bool Execution status.
   */
  public function createList(string $fqdnName, string $style = 'private-default'):bool
  {
    // replace the first dot by @ if no @ is present
    if (strpos($fqdnName, '@') === false) {
      $fqdnName[strpos($fqdnName, '.')] = '@';
    }
    /* $response = */ $this->restClient->post('/3.1/lists', [
      'json' => [
        'fqdn_listname' => $fqdnName,
        'style_name' => $style,
      ],
      'auth' => $this->restAuth,
    ]);
    return true;
  }

  /**
   * Delete an existing list.
   *
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @return bool Execution status.
   */
  public function deleteList(string $listId):bool
  {
    $listId = $this->ensureListId($listId);
    if (empty($listId)) {
      return false;
    }
    /* $response = */ $this->restClient->delete('/3.1/lists/' . $listId, [
      'auth' => $this->restAuth,
    ]);
    return true;
  }

  /**
   * Rename an existing list.
   *
   * Unfortunately, changing the fqdn (email address) is not supported or at
   * least to exposed to the API by mm3. So we do not actually change the
   * address of the list, but just the display name and add the desired
   * email-address to the list of acceptable aliases and then: how to add an
   * email-alias?
   *
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @param null|string $newFqdn The new name.
   *
   * @param null|string $newDisplayName The new display name.
   *
   * @return bool Execution status.
   */
  public function renameList(string $listId, ?string $newFqdn = null, ?string $newDisplayName = null)
  {
    $listConfig = $this->getListConfig($listId);
    $acceptableAliases = $listConfig[self::LIST_CONFIG_ACCEPTABLE_ALIASES];
    $displayName = $listConfig[self::LIST_CONFIG_DISPLAY_NAME];
    $fqdnListName = $listConfig[self::LIST_CONFIG_FQDN_LISTNAME];
    if (!empty($newFqdn) && $fqdnListName != $newFqdn) {
      // - add a new entry to the acceptable aliases entries
      // - add an email alias

      $acceptableAliases[] = $newFqdn;
      $this->addEmailAlias(fromAddress: $newFqdn, toAddress: $fqdnListName);
    }
    if (!empty($newDisplayName)) {
      $displayName = $newDisplayName;
    }
    /* $response = */ $this->restClient->patch(
      '/3.1/lists/' . $listId . '/config', [
        'json' => [
          self::LIST_CONFIG_DISPLAY_NAME => $displayName,
          self::LIST_CONFIG_ACCEPTABLE_ALIASES => $acceptableAliases,
        ],
        'auth' => $this->restAuth,
      ]);
    return true;
  }

  /**
   * Somehow add an email alias to the mail system ...
   *
   * @param string $fromAddress Incoming email address.
   *
   * @param string $toAddress Resulting email address.
   *
   * @return void
   */
  private function addEmailAlias(string $fromAddress, string $toAddress):void
  {
    throw new RuntimeException($this->l->t('Adding email alias is not yet supported'));
  }

  /**
   * Configure the given list as an announcements-only list.
   *
   * That is:
   * - all postings are moderated
   * - allow_list_posts set to false (no list-post header)
   * -
   * - unsubscription is done without needing further confirmation from the user
   * - subscription is unmoderated, but after user confirmation
   * - $posters are added as list-members with the privilege to post to the list
   * - the archive is public
   *
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @param string|array $owners Array of owners or the single owner as string.
   *
   * @param string|array $moderators Array of moderators or the single moderator as string.
   *
   * @param string|array $posters Array of posters or the single posters as
   * string. "posters" are the email addresses which are allowed to post to
   * the list without moderation.
   *
   * @return void
   */
  public function configureAnnouncementsList(string $listId, $owners = [], $moderators = [], $posters = []):void
  {
    $listId = $this->ensureListId($listId);
    $config = [
      'allow_list_posts' => false,
      'subscription_policy' => 'confirm',
      'unsubscription_policy' => 'open', // check keyword
      'default_member_action' => self::MEMBER_MODERATION_HOLD,
      'default_nonmember_action' => self::MEMBER_MODERATION_HOLD,
      'archive_policy' => 'public',
      'advertised' => true,
      'digests_enabled' => false,
      'preferred_language' => $this->getLanguage($this->appLocale()),
      'max_message_size' => 0,
    ];
    $this->setListConfig($listId, $config);

    $owners = is_array($owners) ? $owners : [ $owners ];
    $moderators = is_array($moderators) ? $moderators : [ $moderators ];
    $posters = is_array($posters) ? $posters : [ $posters ];

    $users = [
      self::ROLE_MEMBER => $posters,
      self::ROLE_OWNER => $owners,
      self::ROLE_MODERATOR => $moderators,
    ];

    foreach (self::ROLES as $role) {
      foreach ($users[$role] as $key => $subscriber) {
        if (strpos($key, '@') !== false) {
          $displayName = $subscriber;
          $subscriber = $key;
          $subscriptionData = [
            self::SUBSCRIBER_EMAIL => $subscriber,
            self::MEMBER_DISPLAY_NAME => $displayName,
          ];
        } else {
          $subscriptionData = [
            self::SUBSCRIBER_EMAIL => $subscriber,
          ];
        }
        $subscriptionData[self::ROLE] = $role;
        if (empty($this->getSubscription($listId, $subscriber, $role))) {
          $this->subscribe($listId, subscriptionData: $subscriptionData);
        }
      }
    }

    foreach ($posters as $key => $subscriber) {
      $subscriber = strpos($key, '@') === false ? $subscriber : $key;
      $this->patchMember($listId, $subscriber, self::ROLE_MEMBER, [
        self::MEMBER_MODERATION_KEY => self::MEMBER_MODERATION_ACCEPT,
      ]);
    }
  }

  /**
   * Install a message template.
   *
   * @param string $listId List-id or FQDN. If FQDN, an additional query is
   * necessary to retrieve the list-id from the server.
   *
   * @param string $template The name of the message template to set.
   *
   * @param null|string $uri The url to the template.
   *
   * @return bool Execution status.
   */
  public function setMessageTemplate(string $listId, string $template, ?string $uri):bool
  {
    $listId = $this->ensureListId($listId);
    if (empty($listId)) {
      return false;
    }

    if ($uri === null) {
      // delete
      try {
        /* $response = */ $this->restClient->delete('/3.1/lists/' . $listId . '/uris/' . $template, [
          'auth' => $this->restAuth,
        ]);
      } catch (\GuzzleHttp\Exception\ClientException $e) {
        if ($e->getResponse()->getStatusCode() == 404) {
          // ignore
        } else {
          $this->logException($e, 'Deleting template ' . $template . ' failed');
          return false;
        }
      }
    } else {
      /* $response = */ $this->restClient->patch('/3.1/lists/' . $listId . '/uris', [
        'json' => [
        $template => $uri,
        ],
        'auth' => $this->restAuth,
      ]);
    }
    return true;
  }

  /**
   * Fetch the list-id of the given list. Normally it is just the
   * email-address with @ replaced by a dot.
   *
   * @param string $fqdnName The list FQDN.
   *
   * @return string The list id.
   */
  public function getListId(string $fqdnName)
  {
    if (empty($this->listIdByFqdn[$fqdnName])) {
      $listInfo = $this->getListInfo($fqdnName);
      if (empty($listInfo)) {
        return null;
      }
      $this->listIdByFqdn[$fqdnName] = $listInfo['list_id'];
    }
    return $this->listIdByFqdn[$fqdnName];
  }

  /**
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @return The configuration URL.
   */
  public function getConfigurationUrl(string $listId):string
  {
    $listId = $this->ensureListId($listId);
    return $this->getConfigValue(ConfigService::MAILING_LIST_CONFIG['web']) . '/postorius/lists/' . $listId;
  }

  /**
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @return The archive url.
   */
  public function getArchiveUrl(string $listId):string
  {
    $listId = $this->ensureListId($listId);
    return $this->getConfigValue(ConfigService::MAILING_LIST_CONFIG['web']) . '/hyperkitty/list/' . $listId;
  }

  /**
   * Find the basic information about a subscription for the given
   * email.
   *
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @param string $subscriptionAddress The email address to query.
   *
   * @param string $role Subscription role (member vs. moderator vs owner).
   *
   * @return bool|array The subscription info.
   */
  public function getSubscription(string $listId, string $subscriptionAddress, string $role = self::ROLE_MEMBER)
  {
    $listId = $this->ensureListId($listId);
    if (empty($listId)) {
      return false;
    }

    $post = [
      'list_id' => $listId,
      'subscriber' => $subscriptionAddress,
      self::ROLE => $role,
    ];

    $response = $this->restClient->post(
      '/3.1/members/find', [
        'json' => $post,
        'auth' => $this->restAuth,
      ]);
    if (empty($response->getBody())) {
      return null;
    }
    $response = json_decode($response->getBody(), true);

    $result = [];
    foreach (($response['entries'] ?? []) as $entry) {
      $result[$entry[self::ROLE]] = $entry;
      $this->selfLinkBySubscription[$listId][$subscriptionAddress][$entry[self::ROLE]] = $entry[self::SUBSCRIPTION_SELF_LINK];
    }
    return $result;
  }

  /**
   * Get or check for a pending invitation (=== email confirmation request)
   * for the given subscription-address.
   *
   * It is not possible to distinguish between an invitation and an email
   * confirmation. The difference is that invitations are not moderated after
   * the email address has been confirmed, but subscription requests may be
   * moderated.
   *
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @param string $subscriptionAddress The email address to query.
   *
   * @return array|bool Exectution status or the requested information.
   */
  public function getSubscriptionRequest(string $listId, string $subscriptionAddress):mixed
  {
    $listId = $this->ensureListId($listId);
    if (empty($listId)) {
      return false;
    }

    $response = $this->restClient->get(
      '/3.1/lists/' . $listId . '/requests', [
        'auth' => $this->restAuth,
      ]);
    if (empty($response->getBody())) {
      return false;
    }
    $response = json_decode($response->getBody(), true);
    $result = [];
    foreach (($response['entries'] ?? []) as $listRequest) {
      if ($listRequest['email'] === $subscriptionAddress) {
        $result[$listRequest['token_owner']] = $listRequest;
      }
    }
    return $result;
  }

  /**
   * Dispose any pending subscription request with the given action. In
   * principle there should only be one pending request, so this should be ok.
   *
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @param string $subscriptionAddress The email address to query.
   *
   * @param string $action The action to perform, see self::MODERATION_ACTIONS.
   *
   * @param null|string $reason Optional reason to mail to the $subscriptionAddress.
   *
   * @return bool Execution status.
   */
  public function handleSubscriptionRequest(string $listId, string $subscriptionAddress, string $action, ?string $reason = null)
  {
    $listId = $this->ensureListId($listId);
    if (empty($listId)) {
      return false;
    }
    $requests = $this->getSubscriptionRequest($listId, $subscriptionAddress);
    if (count($requests) > 1) {
      throw new RuntimeException($this->l->t('More than one pending subscription request, bailing out.'));
    }
    foreach ($requests as $request) {
      $token = $request['token'];
      $postData = [
        'action' => $action,
      ];
      if (!empty($reason) && $action == self::MODERATION_ACTION_REJECT) {
        $postData['reason'] = $reason;
      }
      /* $response = */ $this->restClient->post(
        '/3.1/lists/' . $listId . '/requests/' . $token, [
          'json' => $postData,
          'auth' => $this->restAuth,
        ]);
    }
    return true;
  }

  /**
   * Return a brief status for the requested list and email address
   *
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @param string $subscriptionAddress The email address to query.
   *
   * @return string One of self::STATUS_UNSUBSCRIBED, self::STATUS_SUBSCRIBED,
   * self::STATUS_INVITED, self::STATUS_WAITING;
   *
   * @throws ConnectException
   */
  public function getSubscriptionStatus(string $listId, string $subscriptionAddress):string
  {
    self::t('unsubscribed');
    self::t('subscribed');
    self::t('invited');
    self::t('waiting');
    $subscription = $this->getSubscription($listId, $subscriptionAddress);
    if (!empty($subscription[MailingListsService::ROLE_MEMBER])) {
      return self::STATUS_SUBSCRIBED;
    } else {
      // check for pending invitations or waiting membership-requests
      $subscriptionRequest = $this->getSubscriptionRequest($listId, $subscriptionAddress);
      if (!empty($subscriptionRequest['subscriber'])) {
        return self::STATUS_INVITED;
      } elseif (!empty($subscriptionRequest['moderator'])) {
        return self::STATUS_WAITING;
      }
    }
    return self::STATUS_UNSUBSCRIBED;
  }

  /**
   * Subscribe a victim to the email-list.
   *
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @param null|string $email If given the data for the 'subscriber' key of
   * the $subscriptionData.
   *
   * @param null|string $displayName If given the data for the 'display_name'
   * key of the $subscriptionData.
   *
   * @param null|string $role If given the data for the self::ROLE key of the
   * $subscriptionData.
   *
   * @param array $subscriptionData The data array which at least the field
   * 'subscriber' which is the email-address to subscribe. It should also
   * contain a display name. All other parameters understood by the rest-API
   * are passed on the the list-server. The 'list_id' is overridden by the
   * first parameter.
   *
   * @return bool Execution status.
   */
  public function subscribe(string $listId, ?string $email = null, ?string $displayName = null, ?string $role = null, array $subscriptionData = [])
  {
    if (!empty($email)) {
      $subscriptionData['subscriber'] = $email;
    }
    if (!empty($displayName)) {
      $subscriptionData['display_name'] = $displayName;
    }
    if (!empty($role)) {
      $subscriptionData[self::ROLE] = $role;
    }
    if (empty($subscriptionData['subscriber'])) {
      throw new InvalidArgumentException($this->l->t('Missing subscriber email-address.'));
    }

    $listId = $this->ensureListId($listId);
    if (empty($listId)) {
      return false;
    }

    $subscriptionData = array_merge(self::DEFAULT_SUBSCRIPTION_DATA, $subscriptionData);
    $subscriptionData['list_id'] = $listId;

    $quirkDeliveryStatus = false;
    if (($subscriptionData[self::MEMBER_DELIVERY_STATUS] ?? self::DELIVERY_STATUS_ENABLED)
        !=
        self::DELIVERY_STATUS_ENABLED) {
      $coreVersion = [];
      $this->getServerVersions($coreVersion);
      if ($coreVersion['combined'] < self::combinedVersion(3, 3, 4)) {
        $quirkDeliveryStatus = true;
        $deliveryStatus = $subscriptionData[self::MEMBER_DELIVERY_STATUS];
        unset($subscriptionData[self::MEMBER_DELIVERY_STATUS]);
      }
    }

    foreach ($subscriptionData as $key => $value) {
      // bloody Python rest implementation
      if ($value === true) {
        $subscriptionData[$key] = 'True';
      } elseif ($value === false) {
        $subscriptionData[$key] = 'False';
      }
    }
    /* $response = */ $this->restClient->post('/3.1/members', [
      'json' => $subscriptionData,
      'auth' => $this->restAuth,
    ]);

    if ($quirkDeliveryStatus) {
      $this->setSubscriptionPreferences(
        $listId,
        $subscriptionData[self::SUBSCRIBER_EMAIL],
        preferences: [ MailingListsService::MEMBER_DELIVERY_STATUS => $deliveryStatus ],
        role: $role ?? self::ROLE_MEMBER,
      );
    }

    return true;
  }

  /**
   * Just send an invitation.
   *
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @param string $email The subscriber email to send the invitation to.
   *
   * @param null|string $displayName The subscribers display-name.
   *
   * @return bool Execution status.
   */
  public function invite(string $listId, string $email, ?string $displayName = null):bool
  {
    $subscriptionData = [
      'subscriber' => $email,
      'invitation' => true,
    ];
    if (!empty($displayName)) {
      $subscriptionData['display_name'] = $displayName;
    }
    return $this->subscribe($listId, subscriptionData: $subscriptionData);
  }

  /**
   * Unsubscribe the given email-address.
   *
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @param string $subscriber The email-address of the subscriber.
   *
   * @param string $role Role of the subscription.
   *
   * @param bool $silent If set to \true then disable sending for confirmation messages and the like.
   *
   * @return bool Execution status.
   */
  public function unsubscribe(string $listId, string $subscriber, string $role = self::ROLE_MEMBER, bool $silent = false):bool
  {
    $listId = $this->ensureListId($listId);
    if (empty($listId)) {
      return false;
    }
    $subscriptionData = $this->getSubscription($listId, $subscriber);
    $selfLink = $subscriptionData[$role][self::SUBSCRIPTION_SELF_LINK] ?? null;
    if (empty($selfLink)) {
      return false;
    }
    if ($silent) {
      $unsubscriptionData = [
        self::PRE_APPROVED => true,
        self::PRE_CONFIRMED => true,
      ];
      // unfortunately there is only a global "send goodbye message" flag for
      // the list ...
      $this->setListConfig($listId, self::SEND_GOODBYE_MESSAGE, false);
    } else {
      $unsubscriptionData = [];
    }
    foreach ($unsubscriptionData as $key => $value) {
      // bloody Python rest implementation
      if ($value === true) {
        $unsubscriptionData[$key] = 'True';
      } elseif ($value === false) {
        $unsubscriptionData[$key] = 'False';
      }
    }
    /* $result = */ $this->restClient->delete($selfLink, [
      'auth' => $this->restAuth,
      'json' => $unsubscriptionData,
    ]);
    if ($silent) {
      // undo
      $this->setListConfig($listId, self::SEND_GOODBYE_MESSAGE, true);
    }
    return true;
  }

  /**
   * Fetch the subscription preferences.
   *
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @param string $subscriber The email-address of the subscriber.
   *
   * @param string $role Role of the subscription.
   *
   * @return array The preferences data or null in case of error.
   */
  public function getSubscriptionPreferences(string $listId, string $subscriber, string $role = self::ROLE_MEMBER):?array
  {
    $selfLink = $this->selfLinkBySubscription[$listId][$subscriber][$role] ?? null;
    if (empty($selfLink)) {
      $this->getSubscription($listId, $subscriber, $role);
      $selfLink = $this->selfLinkBySubscription[$listId][$subscriber][$role] ?? null;
    }
    if (empty($selfLink)) {
      return null;
    }

    $response = $this->restClient->get($selfLink . '/preferences', [
      'auth' => $this->restAuth,
    ]);
    if (empty($response->getBody())) {
      return null;
    }
    $response = json_decode($response->getBody(), true);
    if (empty($response[self::MEMBER_DELIVERY_STATUS])) {
      $response[self::MEMBER_DELIVERY_STATUS] = self::DELIVERY_STATUS_ENABLED;
    }
    return $response;
  }

  /**
   * Set the subscription preferences.
   *
   * @param string $listId The list-id or FQDN. If a list-fqdn, then an
   * additional query is needed to retrieve the list-id from the server.
   *
   * @param string $subscriber The email-address of the subscriber.
   *
   * @param array $preferences Preferences data array.
   *
   * @param string $role Role of the subscription.
   *
   * @return bool Execution status.
   */
  public function setSubscriptionPreferences(
    string $listId,
    string $subscriber,
    array $preferences = [],
    string $role = self::ROLE_MEMBER,
  ) {
    $selfLink = $this->selfLinkBySubscription[$listId][$subscriber][$role] ?? null;
    if (empty($selfLink)) {
      $this->getSubscription($listId, $subscriber, $role);
      $selfLink = $this->selfLinkBySubscription[$listId][$subscriber][$role] ?? null;
    }
    if (empty($selfLink)) {
      return false;
    }
    /* $response = */ $this->restClient->patch($selfLink . '/preferences', [
      'json' => $preferences,
      'auth' => $this->restAuth,
    ]);
    return true;
  }

  /**
   * Find members matching criteria. Default is to return all members.
   *
   * @param string $listId The mailing list id to get the information for.
   *
   * @param array $criteria Search criteria in order to restrict the search.
   *
   * @param bool $flat Return the result as flat array of just email addresses.
   *
   * @return array The array of members.
   */
  public function findMembers(string $listId, array $criteria = [], bool $flat = false):array
  {
    $criteria = array_merge(self::DEFAULT_MEMBER_SEARCH_CRITERIA, $criteria);

    $criteria['list_id'] = $listId;

    $response = $this->restClient->post('/3.1/members/find', [
      'json' => $criteria,
      'auth' => $this->restAuth,
    ]);

    if (empty($response->getBody())) {
      return null;
    }
    $response = json_decode($response->getBody(), true);

    $response['entries'] = $response['entries'] ?? [];

    foreach ($response['entries'] as $member) {
      $this->selfLinkBySubscription[$listId][$member['email']][$member[self::ROLE]] = $member[self::SUBSCRIPTION_SELF_LINK];
    }

    if ($flat) {
      $members = [];
      foreach ($response['entries'] as $member) {
        $members[] = $member['email'];
      }
      return $members;
    }

    return $response;
  }

  /**
   * Patch the membership config for the given email address. In particular,
   * it is possible to set the moderation action and the delivery address.
   *
   * @param string $listId The mailing list id to get the information for.
   *
   * @param string $subscriber The email-address of the subscriber.
   *
   * @param string $role The member-ship role.
   *
   * @param array $data The to-be-patched-in data.
   *
   * @return bool Execution status.
   */
  public function patchMember(string $listId, string $subscriber, string $role, array $data):bool
  {
    $selfLink = $this->selfLinkBySubscription[$listId][$subscriber][$role] ?? null;
    if (empty($selfLink)) {
      $this->getSubscription($listId, $subscriber, $role);
      $selfLink = $this->selfLinkBySubscription[$listId][$subscriber][$role] ?? null;
    }
    if (empty($selfLink)) {
      return false;
    }
    /* $response = */ $this->restClient->patch($selfLink, [
      'json' => $data,
      'auth' => $this->restAuth,
    ]);
    return true;
  }

  /**
   * Generate the full path to the given templates leaf-directory.
   *
   * @param string $leafDirectory Leaf-component,
   * e.g. self::TEMPLATE_TYPE_ANNOUNCEMENTS or self::TEMPLATE_TYPE_PROJECTS.
   *
   * @return string The full path for the given directory.
   */
  public function templateFolderPath(string $leafDirectory):string
  {
    // relative path
    $l = $this->appL10n();
    $components = array_map(function($path) {
      return Util::dashesToCamelCase($this->transliterate($path), capitalizeFirstCharacter: true, dashes: '_- ');
    }, array_filter([
      $l->t('mailing lists'),
      $l->t('auto-responses'),
      $l->t($leafDirectory),
    ]));
    array_unshift($components, $this->getDocumentTemplatesPath());
    $folderPath = implode('/', $components);
    if ($folderPath[0] != '/') {
      $folderPath = '/' . $folderPath;
    }
    return $folderPath;
  }

  /**
   * Ensure that the given folder exists and is anonymously shared.
   *
   * @param string $folderName The template folder to check for.The $folderName is
   * always treated relative to the shared orchestra file-space. If
   * $folderName is an absolute path, it is still relative to the shared
   * file-space of the orchestra. If $folderName is a relative path then it is
   * treated as relative to the configured document-templates folder.
   *
   * @return null|string The URL in order to access the folder.
   */
  public function ensureTemplateFolder(string $folderName):?string
  {
    $shareOwnerUid = $this->getConfigValue('shareowner');
    $folderPath = $folderName[0] != '/'
      ? $folderPath = $this->templateFolderPath($folderName)
      : $folderName;

    // try to create or use the folder and share it by a public link
    $result = $this->sudo($shareOwnerUid, function(string $shareOwnerUid) use ($folderPath) {

      /** @var IRootFolder $rootFolder */
      $rootFolder = $this->di(IRootFolder::class);

      // $shareOwner = $this->user();
      $rootView = $rootFolder->getUserFolder($shareOwnerUid);

      if ($rootView->nodeExists($folderPath)) {
        $node = $rootView->get($folderPath);
        if ($node->getType() != FileInfo::TYPE_FOLDER || !$node->isShareable()) {
          try {
            $node->delete();
          } catch (\Throwable $t) {
            $this->logException($t);
            return null;
          }
        }
      }

      if (!$rootView->nodeExists($folderPath) && !$rootView->newFolder($folderPath)) {
        return null;
      }

      $node = $rootView->nodeExists($folderPath) ? $rootView->get($folderPath) : null;
      if (empty($node) || $node->getType() != FileInfo::TYPE_FOLDER) {
        throw new RuntimeException($this->l->t('Folder \`%s\' could not be created', [$folderPath]));
      }

      // Now it should exist as directory and $node should contain its file-info

      /** @var SimpleSharingService $sharingService */
      $sharingService = $this->di(SimpleSharingService::class);

      if ($node) {
        $url = $sharingService->linkShare($node, $shareOwnerUid, sharePerms: \OCP\Constants::PERMISSION_READ|\OCP\Constants::PERMISSION_SHARE);
        if (empty($url)) {
          return null;
        }
      } else {
        $this->logError('No file info for ' . $folderPath);
        return null;
      }

      return $url;
    });

    return $result;
  }

  /**
   * Install auto-response templates from the specified cloud-folder for the
   * given list-id.
   *
   * @param string $listId
   *
   * @param string $folderName
   *
   * @return array An array of the successfully installed templates.
   */
  public function installListTemplates(string $listId, string $folderName)
  {
    if ($folderName[0] != '/') {
      $templateFolderPath = $this->templateFolderPath($folderName);
      $baseTemplatePath = $this->templateFolderPath(MailingListsService::TEMPLATE_TYPE_UNSPECIFIC);
      $baseFolderShareUri = $this->ensureTemplateFolder($baseTemplatePath);
      $templateFolderBase = basename($templateFolderPath);
      $folderShareUri = $baseFolderShareUri . '/download?path=/' . $templateFolderBase;
    } else {
      $templateFolderPath = $folderName;
      $baseFolderShareUri = $this->ensureTemplateFolder($templateFolderPath);
      $folderShareUri = $baseFolderShareUri . '/download?path=/';
    }

    /** @var UserStorage $userStorage */
    $userStorage = $this->di(UserStorage::class);

    $templates = [];

    /** @var \OCP\Files\Folder $node */
    foreach ($userStorage->getFolder($templateFolderPath)->getDirectoryListing() as $node) {
      if ($node->getType() != \OCP\Files\FileInfo::TYPE_FILE) {
        continue;
      }
      $mimeType = $node->getMimetype();
      if ($mimeType != 'text/plain' && $mimeType != 'text/markdown') {
          continue;
      }
      $pathInfo = pathinfo($node->getPath());
      $template = $pathInfo['filename'];
      if (!str_starts_with($template, MailingListsService::TEMPLATE_FILE_PREFIX)) {
          continue;
      }
      $nodeBase = $pathInfo['basename'];
      $templateUri = $folderShareUri . '&files=' . $nodeBase;
      try {
        $result = $this->setMessageTemplate($listId, $template, $templateUri);
      } catch (\Throwable $t) {
        $this->logException($t, 'Unable to install auto-response ' . $template);
        $result = false;
      }
      if ($result) {
        $templates[] = $template;
      }
    }

    return $templates;
  }

  /**
   * Ensure that the given string is a list-id, if it is a FQDN then try to
   * retrieve the list-id.
   *
   * @param string $identifier List-id of list-FQDN.
   *
   * @return null|string The List-id or null.
   */
  private function ensureListId(string $identifier):?string
  {
    if (strpos($identifier, '@') !== false) {
      $listId = $this->getListId($identifier);
      if (empty($listId)) {
        return null;
      }
    } else {
      $listId = $identifier;
    }
    return $listId;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
