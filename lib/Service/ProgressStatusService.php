<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use OCP\IUser;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Common\IProgressStatus;
use OCA\CAFEVDB\Common\DatabaseProgressStatus;
use OCA\CAFEVDB\Common\PlainFileProgressStatus;

/** Factory for progress status implementation. */
class ProgressStatusService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  //static private $progressStatusImplementation = DatabaseProgressStatus::class;
  private static $progressStatusImplementation = PlainFileProgressStatus::class;

  /** @var string */
  private $appName;

  /** @var IAppContainer */
  private $appContainer;

  // phpcs:disabled Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    $appName,
    IAppContainer $appContainer,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->appName = $appName;
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
  }
  // phpcs:enable

  /**
   * @param mixed $start
   *
   * @param mixed $stop
   *
   * @param mixed $data
   *
   * @param mixed $id
   *
   * @return IProgressStatus
   */
  public function create(mixed $start, mixed $stop, mixed $data = null, mixed $id = null):IProgressStatus
  {
    $progressStatus = $this->appContainer->get(self::$progressStatusImplementation);
    $progressStatus->bind($id);
    $progressStatus->update($start, $stop, $data);

    return $progressStatus;
  }

  /**
   * @param mixed $id
   *
   * @return IProgressStatus
   */
  public function get(mixed $id):IProgressStatus
  {
    $progressStatus = $this->appContainer->get(self::$progressStatusImplementation);
    $progressStatus->bind($id);
    return $progressStatus;
  }
}
