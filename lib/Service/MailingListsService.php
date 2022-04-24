<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service;

use GuzzleHttp\Client as RestClient;

use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;

use OCA\CAFEVDB\Common\Util;

/** Handle participant mailing-list services. */
class MailingListsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  private const DEFAULT_SUBSCRIPTION_DATA = [
    'pre_verified' => true,
    'pre_confirmed' => true,
    'pre_approved' => true,
    'send_welcome_message' => true,
    'role' => self::ROLE_MEMBER,
  ];

  const TEMPLATE_DIR_MAILING_LISTS = 'mailing lists';
  const TEMPLATE_DIR_AUTO_RESPONSES = 'auto responses';
  const TYPE_ANNOUNCEMENTS = 'announcements';
  const TYPE_PROJECTS = 'projects';

  const ROLE_MEMBER = 'member';
  const ROLE_MODERATOR = 'moderator';
  const ROLE_OWNER = 'owner';

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
    'role' => self::ROLE_MEMBER,
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
   */
  public function getServerConfig()
  {
    $response = $this->restClient->get(
      '/3.1/system/configuration', [
        'auth' => $this->restAuth,
      ]);
    return empty($response->getBody()) ? null : json_decode($response->getBody(), true);
  }

  /**
   * Obtain the server versions.
   */
  public function getServerVersions(?array &$coreVersion = null)
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

  static private function combinedVersion(int $major, int $minor, int $revision)
  {
    return $revision + 100 + ($minor + 100 * $major);
  }

  /**
   * Fetch the list configuration from the server. Returns null if the list
   * does not exist.
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

  public function setListConfig(string $fqdnName, $config, mixed $value = null)
  {
    if (!is_array($config)) {
      if ($value === true) {
        $value = 'True';
      } else if ($value === false) {
        $value = 'False';
      }
      $config = [ $config => $value ];
    }
    $response = $this->restClient->patch(
      '/3.1/lists/' . $fqdnName . '/config', [
        'json' => $config,
        'auth' => $this->restAuth,
      ]);
    return true;
  }

  /** Fetch the brief list-info. */
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

  /** Create a non-existing list */
  public function createList(string $fqdnName, string $style = 'private-default')
  {
    // replace the first dot by @ if not @ is present
    if (strpos($fqdnName, '@') === false) {
      $fqdnName[strpos($fqdnName, '.')] = '@';
    }
    $response = $this->restClient->post('/3.1/lists', [
      'json' => [
        'fqdn_listname' => $fqdnName,
        'style_name' => $style,
      ],
      'auth' => $this->restAuth,
    ]);
    return true;
  }

  /** Delete an existing list */
  public function deleteList(string $listId)
  {
    if (empty($listId = $this->ensureListId($listId))) {
      return false;
    }
    $response = $this->restClient->delete('/3.1/lists/' . $listId, [
      'auth' => $this->restAuth,
    ]);
    return true;
  }

  /**
   * Rename an existing list.
   *
   * Unfortunately, changing the fqdn (email address) not supported or at
   * least to exposed to the API by mm3. So we do not actually change the
   * address of the list, but just the display name and add the desired
   * email-address to the list of acceptable aliases and then: how to add an
   * email-alias?
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
    $response = $this->restClient->patch(
      '/3.1/lists/' . $listId . '/config', [
        'json' => [
          self::LIST_CONFIG_DISPLAY_NAME => $displayName,
          self::LIST_CONFIG_ACCEPTABLE_ALIASES => $acceptableAliases,
        ],
        'auth' => $this->restAuth,
      ]);
    return true;
  }

  /** Somehow add an email alias to the mail system ... */
  private function addEmailAlias(string $fromAddress, string $toAddress)
  {
    throw new \RuntimeException($this->l->t('Adding email alias is not yet supported'));
  }

  /**
   * Install a message template.
   *
   * @param string $listId List-id or FQDN. If FQDN, an additional query is
   * necessary to retrieve the list-id from the server.
   */
  public function setMessageTemplate(string $listId, string $template, ?string $uri)
  {
    if (empty($listId = $this->ensureListId($listId))) {
      return false;
    }

    if ($uri === null) {
      // delete
      try {
        $response = $this->restClient->delete('/3.1/lists/' . $listId . '/uris/' . $template, [
          'auth' => $this->restAuth,
        ]);
      } catch (\GuzzleHttp\Exception\ClientException $e) {
        if ($e->getResponse()->getStatusCode() == 404) {
          // ignore
        } else {
          $this->logException($e, 'Deleting tempalte ' . $template . ' failed');
          return false;
        }
      }
    } else {
      $response = $this->restClient->patch('/3.1/lists/' . $listId . '/uris', [
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
   * email-address with @ replaced by a dot
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

  public function getConfigurationUrl(string $listId)
  {
    $listId = $this->ensureListId($listId);
    return $this->getConfigValue(ConfigService::MAILING_LIST_CONFIG['web']) . '/postorius/lists/' . $listId;
  }

  public function getArchiveUrl(string $listId)
  {
    $listId = $this->ensureListId($listId);
    return $this->getConfigValue(ConfigService::MAILING_LIST_CONFIG['web']) . '/hyperkitty/list/' . $listId;
  }

  /**
   * Find the basic information about a subscription for the given
   * email.
   */
  public function getSubscription(string $listId, string $subscriptionAddress, string $role = self::ROLE_MEMBER)
  {
    if (empty($listId = $this->ensureListId($listId))) {
      return false;
    }

    $post = [
      'list_id' => $listId,
      'subscriber' => $subscriptionAddress,
      'role' => $role,
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
      $result[$entry['role']] = $entry;
      $this->selfLinkBySubscription[$listId][$subscriptionAddress][$entry['role']] = $entry[self::SUBSCRIPTION_SELF_LINK];
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
   */
  public function getSubscriptionRequest(string $listId, string $subscriptionAddress)
  {
    if (empty($listId = $this->ensureListId($listId))) {
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
   * @param string $listId
   *
   * @param string $subscriptionAddress
   *
   * @param string $action
   */
  public function handleSubscriptionRequest(string $listId, string $subscriptionAddress, string $action, ?string $reason = null)
  {
    if (empty($listId = $this->ensureListId($listId))) {
      return false;
    }
    $requests = $this->getSubscriptionRequest($listId, $subscriptionAddress);
    if (count($requests) > 1) {
      throw new \RuntimeException($this->l->t('More than one pending subscription request, bailing out.'));
    }
    foreach ($requests as $owner => $request) {
      $token = $request['token'];
      $postData = [
        'action' => $action,
      ];
      if (!empty($reason) && $action == self::MODERATION_ACTION_REJECT) {
        $postData['reason'] = $reason;
      }
      $response = $this->restClient->post(
        '/3.1/lists/' . $listId . '/requests/' . $token
        , [
          'json' => $postData,
          'auth' => $this->restAuth,
        ]);
    }
    return true;
  }

  /**
   * Return a brief status for the requested list and email address
   *
   * @return string One of self::STATUS_UNSUBSCRIBED, self::STATUS_SUBSCRIBED,
   * self::STATUS_INVITED, self::STATUS_WAITING;
   */
  public function getSubscriptionStatus(string $listId, string $subscriptionAddress):string
  {
    $result = self::STATUS_UNSUBSCRIBED;
    $subscription = $this->getSubscription($listId, $subscriptionAddress);
    if (!empty($subscription[MailingListsService::ROLE_MEMBER])) {
      return self::STATUS_SUBSCRIBED;
    } else {
      // check for pending invitations or waiting membership-requests
      $subscriptionRequest = $this->getSubscriptionRequest($listId, $subscriptionAddress);
      if (!empty($subscriptionRequest['subscriber'])) {
        return self::STATUS_INVITED;
      } else if (!empty($subscriptionRequest['moderator'])) {
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
   * @param array $subscriptionData The data array which at least the field
   * 'subscriber' which is the email-address to subscribe. It should also
   * contain a display name. All other parameters understood by the rest-API
   * are passed on the the list-server. The 'list_id' is overridden by the
   * first parameter.
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
      $subscriptionData['role'] = $role;
    }
    if (empty($subscriptionData['subscriber'])) {
      throw new \InvalidArgumentException($this->l->t('Missing subscriber email-address.'));
    }

    if (empty($listId = $this->ensureListId($listId))) {
      return false;
    }

    $subscriptionData = array_merge(self::DEFAULT_SUBSCRIPTION_DATA, $subscriptionData);
    $subscriptionData['list_id'] = $listId;

    $quirkDeliveryStatus = false;
    if (($subscriptionData[self::MEMBER_DELIVERY_STATUS] ?? self::DELIVERY_STATUS_ENABLED)
        !=
        self::DELIVERY_STATUS_ENABLED) {
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
      } else if ($value === false) {
        $subscriptionData[$key] = 'False';
      }
    }
    $response = $this->restClient->post('/3.1/members', [
      'json' => $subscriptionData,
      'auth' => $this->restAuth,
    ]);

    if ($quirkDeliveryStatus) {
      $this->setSubscriptionPreferences(
        $listId,
        $subscriptionData[self::SUBSCRIBER_EMAIL],
        $role ?? self::ROLE_MEMBER,
        [ MailingListsService::MEMBER_DELIVERY_STATUS => $deliveryStatus ]
      );
    }

    return true;
  }

  /** Just send an invitation. */
  public function invite(string $listId, string $email = null, ?string $displayName = null)
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
   */
  public function unsubscribe(string $listId, string $subscriber, string $role = self::ROLE_MEMBER)
  {
    if (empty($listId = $this->ensureListId($listId))) {
      return false;
    }
    $subscriptionData = $this->getSubscription($listId, $subscriber);
    $selfLink = $subscriptionData[$role][self::SUBSCRIPTION_SELF_LINK] ?? null;
    if (empty($selfLink)) {
      return false;
    }
    $result = $this->restClient->delete($selfLink, [
      'auth' => $this->restAuth,
    ]);
    return true;
  }

  public function getSubscriptionPreferences(string $listId, string $subscriber, string $role = self::ROLE_MEMBER)
  {
    if (empty($selfLink = $this->selfLinkBySubscription[$listId][$subscriber][$role] ?? null)) {
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

    return $response;
  }

  public function setSubscriptionPreferences(string $listId, string $subscriber, string $role = self::ROLE_MEMBER, array $preferences = [])
  {
    if (empty($selfLink = $this->selfLinkBySubscription[$listId][$subscriber][$role] ?? null)) {
      $this->getSubscription($listId, $subscriber, $role);
      $selfLink = $this->selfLinkBySubscription[$listId][$subscriber][$role] ?? null;
    }
    if (empty($selfLink)) {
      return false;
    }
    $response = $this->restClient->patch($selfLink . '/preferences', [
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
   */
  public function findMembers(string $listId, array $criteria = [], bool $flat = false)
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
   * Generate the full path to the given templates leaf-directory.
   *
   * @paran string $leafDirectory Leaf-component,
   * e.g. self::TYPE_ANNOUNCEMENTS or self::TYPE_PROJECTS.
   */
  public function templateFolderPath(string $leafDirectory)
  {
    // relative path
    $l = $this->appL10n();
    $components = array_map(function($path) {
      return Util::dashesToCamelCase($this->transliterate($path), capitalizeFirstCharacter: true, dashes: '_- ');
    }, [
      $l->t('mailing lists'),
      $l->t('auto-responses'),
      $leafDirectory,
    ]);
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
   * @param string $folder The template folder to check for.The $folderName is
   * always treated relative to the shared orchestra file-space. If
   * $folderName is an absolute path, it is still relative to the shared
   * file-space of the orchestra. If $folderName is a relative path then it is
   * treated as relative to the configured document-templates folder.
   *
   * @return string The URL in order to access the folder.
   */
  public function ensureTemplateFolder(string $folderName)
  {
    $shareOwnerUid = $this->getConfigValue('shareowner');
    $folderPath = $folderName[0] != '/'
      ? $folderPath = $this->templateFolderPath($folderName)
      : $folderName;

    // try to create or use the folder and share it by a public link
    $result = $this->sudo($shareOwnerUid, function(string $shareOwnerUid) use ($folderPath) {

      /** @var IRootFolder $rootFolder */
      $rootFolder = $this->di(IRootFolder::class);

      $shareOwner = $this->user();
      $rootView = $rootFolder->getUserFolder($shareOwnerUid);

      if ($rootView->nodeExists($folderPath)
          && (($node = $rootView->get($folderPath))->getType() != FileInfo::TYPE_FOLDER
              || !$node->isShareable())) {
        try {
          $node->delete();
        } catch (\Throwable $t) {
          $this->logException($t);
          return null;
        }
      }

      if (!$rootView->nodeExists($folderPath) && !$rootView->newFolder($folderPath)) {
        return null;
      }

      if (!$rootView->nodeExists($folderPath)
          || ($node = $rootView->get($folderPath))->getType() != FileInfo::TYPE_FOLDER) {
        throw new \Exception($this->l->t('Folder \`%s\' could not be created', [$folderPath]));
      }

      // Now it should exist as directory and $node should contain its file-info

      /** @var SimpleSharingService $sharingService */
      $sharingService = $this->di(SimpleSharingService::class);

      if ($node) {
        $url = $sharingService->linkShare($node, $shareOwnerUid, sharePerms: \OCP\Constants::PERMISSION_READ);
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

  // ensure that the given string is a list-id, if it is a FQDN then try to
  // retrieve the list-id.
  private function ensureListId(string $identifier)
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
