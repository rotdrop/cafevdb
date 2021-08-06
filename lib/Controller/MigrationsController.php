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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\MigrationsService;

class MigrationsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  const ALL_MIGRATIONS = 'all';
  const UNAPPLIED_MIGRATIONS = 'unapplied';
  const LATEST_MIGRATION = 'latest';

  /** @var MigrationsService */
  private $migrationsService;

  public function __construct(
    $appName
    , IRequest $request
    , ConfigService $configService
    , MigrationsService $migrationsService
  ) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->migrationsService = $migrationsService;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function get($what)
  {
    switch ($what) {
    case self::ALL_MIGRATIONS:
      return self::dataResponse([ 'migrations' => $this->migrationsService->getAll(), ]);
    case self::UNAPPLIED_MIGRATIONS:
      return self::dataResponse([ 'migrations' => $this->migrationsService->getUnapplied(), ]);
    case self::LATEST_MIGRATION:
      return self::dataResponse([ 'latest' => $this->migrationsService->getLatest(), ]);
    }
    return self::grumble($this->l->t('Unknown Request "%s".', $what));
  }

  /**
   * @NoAdminRequired
   */
  public function serviceSwitch($topic, $subTopic)
  {
    switch ($topic) {
    case 'apply':
      switch ($subTopic) {
      case 'all':
        $this->migrationsService->applyAll();
        break;
      default:
        $version = $subTopic;
        $this->migrationsService->apply($version);
        break;
      }
      break;
    }
    return self::grumble($this->l->t('Unknown Request "%s".', $topic));
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
