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

use OCP\IUser;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Common\IProgressStatus;
use OCA\CAFEVDB\Common\DatabaseProgressStatus;
use OCA\CAFEVDB\Common\PlainFileProgressStatus;

class ProgressStatusService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  //static private $progressStatusImplementation = DatabaseProgressStatus::class;
  static private $progressStatusImplementation = PlainFileProgressStatus::class;

  /** @var string */
  private $appName;

  /** @var IAppContainer */
  private $appContainer;

  public function __construct(
    $appName
    , IAppContainer $appContainer
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appName = $appName;
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * @return IProgressStatus
   */
  public function create($start, $stop, $data = null, $id = null):IProgressStatus
  {
    $progressStatus = $this->appContainer->get(self::$progressStatusImplementation);
    $progressStatus->bind($id);
    $progressStatus->update($start, $stop, $data);

    return $progressStatus;
  }

  /**
   * @param mixed id
   *
   * @return IProgressStatus
   */
  public function get($id):IProgressStatus
  {
    $progressStatus = $this->appContainer->get(self::$progressStatusImplementation);
    $progressStatus->bind($id);
    return $progressStatus;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
