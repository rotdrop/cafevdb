<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  /** Obtain the server configuration, this is rather for testing in
   *  order to check the basic connectivity.
   */
  public function getServerConfiguration()
  {
    $response = $this->restClient->get(
      '/3.1/system/configuration', [
        'auth' => $this->restAuth,
      ]);
    return $response;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
