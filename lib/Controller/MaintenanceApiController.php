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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\AppFramework\OCS;
use OCP\AppFramework\Http\DataResponse;

use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

class MaintenanceApiController extends OCSController
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public function __construct(
    $appName
    , IRequest $request
    , ConfigService $configService
  ) {
    parent::__construct($appName, $request);
    $this->configService = $configService;
    $this->l = $this->l10n();
  }

  /**
   * @CORS
   * @NoCSRFRequired
   * @NoAdminRequired
   * @ServiceAccountRequired
   */
  public function serviceSwitch($operation)
  {
    throw new OCS\OCSNotFoundException;
  }

  /**
   * @CORS
   * @NoCSRFRequired
   * @NoAdminRequired
   * @ServiceAccountRequired
   */
  public function get($topic)
  {
    switch ($topic) {
    case 'Hello':
      return new DataResponse([ 'response' => 'Hello World!' ]);
    }
    throw new OCS\OCSNotFoundException;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
