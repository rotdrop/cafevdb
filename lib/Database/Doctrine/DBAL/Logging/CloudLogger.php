<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Logging;

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Logging\SQLLogger;

use OCP\EventDispatcher\IEventDispatcher;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ConfigService;

/** DBAL logger implementation which logs to the cloud log. */
class CloudLogger implements SQLLogger
{
  use \OCA\RotDrop\Toolkit\Traits\LoggerTrait;

  /** @var IEventDispatcher */
  private $eventDispatcher;

  /** @var \OCA\CAFEVDB\Service\EncryptionService */
  private $encryptionService;

  /** @var bool */
  private $enabled;

  /** @var array|null */
  private $currentQuery = null;

  /** @var float|null */
  public $start = null;

  /** {@inheritdoc} */
  public function __construct(
    EncryptionService $encryptionService,
    IEventDispatcher $eventDispatcher,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->encryptionService = $encryptionService;
    $this->eventDispatcher = $eventDispatcher;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->enabled = false;
    if ($this->encryptionService->bound()) {
      $debugMode = $this->encryptionService->getConfigValue('debugmode', 0);
      $debugMode = (int)filter_var($debugMode, FILTER_VALIDATE_INT, ['min_range' => 0]);
      $this->enabled = 0 != ($debugMode & ConfigService::DEBUG_QUERY);
    } else {
      $this->eventDispatcher->addListener(
        Events\EntityManagerBoundEvent::class,
        function(Events\EntityManagerBoundEvent $event) {
          $debugMode = $this->encryptionService->getConfigValue('debugmode', 0);
          $debugMode = (int)filter_var($debugMode, FILTER_VALIDATE_INT, ['min_range' => 0]);
          $this->enabled = 0 != ($debugMode & ConfigService::DEBUG_QUERY);
        }
      );
    }
  }

  /**
   * Enable logging.
   *
   * @param bool $enable Optional, defaults to true.
   *
   * @return void
   */
  public function enable(bool $enable = true):void
  {
    $this->enabled = $enable;
  }

  /**
   * Disable logging.
   *
   * @param bool $disable Optional, defaults to true.
   *
   * @return void
   */
  public function disable(bool $disable = true):void
  {
    $this->enable(!$disable);
  }

  /**
   * {@inheritdoc}
   *
   * Logs a SQL statement somewhere.
   *
   * @param string              $sql    The SQL to be executed.
   *
   * @param mixed[]|null        $params The SQL parameters.
   *
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
   * {@inheritdoc}
   *
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

    $this->logInfo(print_r($this->currentQuery, true), [], 10, true);

    $this->currentQuery = null;
    $this->start = null;
  }
}
