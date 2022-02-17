<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
/**
 * @file Expose tooltips as AJAY controllers, fetching them by their key.
 */

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Crypto\AsymmetricKeyService;

class EncryptionController extends OCSController
{
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var AsymmetricKeyService */
  private $keyService;

  /** @var IL10N */
  protected $l;

  public function __construct(
    $appName
    , IRequest $request
    , AsymmetricKeyService $keyService
    , ILogger $logger
    , IL10N $l10n
  ) {
    parent::__construct($appName, $request);
    $this->toolTipsService = $toolTipsService;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->keyService = $keyService;
  }

  /**
   * curl -u $(cat ./APITEST-TOKEN) -X GET 'https://anaxagoras.home.claus-justus-heine.de/nextcloud-git/ocs/v2.php/apps/cafevdb/api/v1/maintenance/encryption/recrypt' -H 'Accept: application/json' -H "OCS-APIRequest: true"
   *
   * @CORS
   * @NoCSRFRequired
   * @AuthorizedAdminSetting(settings=OCA\CAFEVDB\Settings\Admin)
   */
  public function getRecryptRequests(?string $userId = null)
  {
    return new DataResponse(
      $this->keyService->getEncryptionRequests()
    );
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
