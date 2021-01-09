<?php
/* Orchestra member, musician and project management application.
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\Projects as Renderer;

class MusiciansController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit */
  protected $pme;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var EntityManager */
  protected $entityManager;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->pme = $phpMyEdit;
    $this->l = $this->l10N();
    $this->setDatabaseRepository(Entities\ProjectParticipant::class);
  }

  /**
   * @NoAdminRequired
   *
   * @param string $topic
   * - change-musician-instruments
   * - change-project-instruments
   * - add-musicians
   */
  public function validate($topic)
  {
    switch ($topic) {
    default:
      break;
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
