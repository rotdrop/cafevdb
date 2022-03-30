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

/** Handle participant mailing-list services. */
class MailingListsService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var string
   * Default rest URI
   */
  private const DEFAULT_REST_URI = 'http://localhost:8001';

  /** @var RestClient */
  private $restClient;

  /** @var array */
  private $restAuth;

  public function __construct(
    ConfigService $configService
  ) {
    $this->configService = $configService;

    $this->restClient = new RestClient([ 'base_uri' => $this->getConfigValue(
      ConfigService::MAILING_LIST_CONFIG['url'],
      self::DEFAULT_REST_URI) ]);
    $this->restAuth = [
      $this->getConfigValue(ConfigService::MAILING_LIST_CONFIG['user']),
      $this->getConfigValue(ConfigService::MAILING_LIST_CONFIG['password']),
    ];
  }

  /**
   * Obtain the list configuration, this is rather for testing in
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

  public function setListConfig(string $fqdnName, $config, ?string $value = null)
  {
    if (!is_array($config)) {
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
  public function getListInfo(string $fqdnName)
  {
    $response = $this->restClient->get(
      '/3.1/lists/' . $fqdnName, [
        'auth' => $this->restAuth,
      ]);
    return empty($response->getBody()) ? null : json_decode($response->getBody(), true);
  }

  /** Create a non-existing list or no-op if list exists */
  public function createList(string $fqdnName)
  {
    $reponse = $this->restClient->post('/3.1/lists', [
      'json' => [ 'fqdn_listname' => $fqdnName ],
      'auth' => $this->restAuth,
    ]);
    return true;
  }

  /**
   * Fetch the list-id of the given list. Normally it is just the
   * email-address with @ replaced by a dot
   */
  public function getListId(string $fqdnName)
  {
    $listInfo = $this->getListInfo($fqdnName);
    return empty($listInfo) ? null : $listInfo['list_id'];
  }

  /**
   * Find the basic information about a subscription for the given
   * email. Return null if not found.
   */
  public function getSubscription(string $listId, string $subscriptionAddress)
  {
    if (strpos($listId, '@') !== false) {
      $listId = $this->getListId($listId);
      if (empty($listId)) {
        return null;
      }
    }
    $response = $this->restClient->post(
      '/3.1/members/find', [
        'json' => [
          'list_id' => $listId,
          'subscriber' => $subscriptionAddress,
        ],
        'auth' => $this->restAuth,
      ]);
    if (empty($response->getBody())) {
      return null;
    }
    $response = json_decode($response->getBody(), true);
    if ($response['total_size'] !== 1) {
      return null;
    }
    return reset($response['entries']);
  }


}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
