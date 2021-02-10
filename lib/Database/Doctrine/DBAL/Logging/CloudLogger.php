<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Logging;

use Doctrine\DBAL\Logging\SQLLogger;
use OCP\ILogger as LoggerInterface;
use OCP\IL10N;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ConfigService;

class CloudLogger implements SQLLogger
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var \OCA\CAFEVDB\Service\EncryptionService */
  private $encryptionService;

  /** @var bool */
  private $enabled;

  /** @var array|null */
  private $currentQuery = null;

  /** @var float|null */
  public $start = null;

  public function __construct(
    EncryptionService $encryptionService
    , LoggerInterface $logger
    , IL10N $l10n
  ) {
    $this->encryptionService = $encryptionService;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->enabled = 0 != ($encryptionService->getConfigValue('debugmode', 0) & ConfigService::DEBUG_QUERY);
  }

  /**
   * Enable logging.
   *
   * @param bool $enable Optional, defaults to true.
   */
  public function enable(bool $enable = true)
  {
    $this->enabled = $enable;
  }

  /**
   * Disable logging.
   *
   * @param bool $disable Optional, defaults to true.
   */
  public function disable($disable = true)
  {
    $this->enable(!$disable);
  }

  /**
   * Logs a SQL statement somewhere.
   *
   * @param string              $sql    The SQL to be executed.
   * @param mixed[]|null        $params The SQL parameters.
   * @param int[]|string[]|null $types  The SQL parameter types.
   *
   * @return void
   */
  public function startQuery($sql, ?array $params = null, ?array $types = null)
  {
    if (!$this->enabled) {
      return;
    }

    $this->start = microtime(true);
    $this->currentQuery = ['sql' => $sql, 'params' => $params, 'types' => $types, 'executionMS' => 0];
  }

  /**
   * Marks the last started query as stopped. This can be used for timing of queries.
   *
   * @return void
   */
  public function stopQuery()
  {
    if (!$this->enabled || empty($this->currentQuery)) {
      return;
    }

    $this->currentQuery['executionMS'] = microtime(true) - $this->start;

    $this->logInfo(print_r($this->currentQuery, true), [], 9);

    $this->currentQuery = null;
    $this->start = null;
  }

}
