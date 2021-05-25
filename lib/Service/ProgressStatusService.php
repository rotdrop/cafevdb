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

use OCA\CAFEVDB\Database\Cloud\Synchronized\SynchronizedProgressStatus as ProgressStatus;

class ProgressStatusService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var IDBConnection */
  private $db;

  /** @var string */
  private $userId;

  /** @var string */
  private $appName;

  public function __construct(
    IDBConnection $db
    , $appName
    , $userId
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->db = $db;
    $this->appName = $appName;
    $this->userId = $userId;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * @return ProgressStatus
   */
  public function create($start, $stop, $data = null, $id = null):ProgressStatus
  {
    $progressStatus = new ProgressStatus($this->db, $this->appName, $this->userId, $id);
    $mergeData = [
      'userId' => $this->userId,
      'current' => $start,
      'target' => $stop,
    ];
    if (!empty($data)) {
      if (is_array($data)) {
        $data = json_encode($data);
      }
      $mergeData['data'] = $data;
    }
    $progressStatus->merge($mergeData);
    return $progressStatus;
  }

  /**
   * @param int id
   *
   * @return ProgressStatus
   */
  public function get(int $id):ProgressStatus
  {
    $progressStatus = new ProgressStatus($this->db, $this->appName, $this->userId, $id);
    return $progressStatus;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
