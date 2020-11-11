<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;
use OCP\ICache;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Common\Util;

class ImagesController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var IL10N */
  private $l;

  /** @var CalDavService */
  private $calDavService;

  /** @var \OCP\ICache */
  private $fileCache;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , CalDavService $calDavService
    , ICache $fileCache
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->calDavService = $calDavService; // ? why
    $this->fileCache = $fileCache;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function get($joinTable, $ownerId)
  {
    if ($joinTable == 'cache') {

    case 'cache':
      $cacheKey = $ownerId;
      $imageData = $this->fileCache->get($cacheKey);
      return new DataDownloadResponse();
    default:

    }
    return self::grumble($this->l->t('Unknown Request'));
  }

  /**
   * @NoAdminRequired
   */
  public function post($joinTable, $ownerId)
  {
    return self::grumble($this->l->t('Unknown Request'));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
