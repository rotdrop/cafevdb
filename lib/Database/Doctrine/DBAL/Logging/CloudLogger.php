<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2025 Claus-Justus Heine
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

use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Settings\ConfigConstants;
// use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Logging\SQLLogger;
use OCA\CAFEVDB\Wrapped\Firehed\DbalLogger\QueryLogger;

/** DBAL logger implementation which logs to the cloud log. */
class CloudLogger implements QueryLogger
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var bool */
  private $enabled;

  /** @var array|null */
  private $currentQuery = null;

  /**
   * A regular expression. If set only matching queries will be logger.
   */
  private ?string $sqlFilter = null;

  /** @var float|null */
  public $start = null;

  /** {@inheritdoc} */
  public function __construct(
    private EncryptionService $encryptionService,
    private IEventDispatcher $eventDispatcher,
    protected ILogger $logger,
    protected IL10N $l,
  ) {
    $this->enabled = false;
    if ($this->encryptionService->bound()) {
      $this->setup();
    } else {
      $this->eventDispatcher->addListener(
        Events\EntityManagerBoundEvent::class,
        fn(Events\EntityManagerBoundEvent $event) => $this->setup(),
      );
    }
  }

  /** @return void */
  private function setup(): void
  {
    $debugMode = $this->encryptionService->getUserValue(EnumPersonalSettingsKey::DEBUG_MODE, 0);
    $debugMode = (int)filter_var($debugMode, FILTER_VALIDATE_INT, ['min_range' => 0]);
    $this->enabled = 0 != ($debugMode & ConfigConstants::DEBUG_QUERY);
    $this->sqlFilter = $this->encryptionService->getUserValue(EnumPersonalSettingsKey::DEBUG_QUERY_SQL_FILTER, null);
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
   * @param ?string $sqlFilter
   *
   * @return void
   */
  public function setSqlFilter(?string $sqlFilter): void
  {
    $this->sqlFilter = $sqlFilter;
  }

  /** @return ?string */
  public function getSqlFilter(): ?string
  {
    return $this->sqlFilter;
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

    if (!empty($this->sqlFilter) && !preg_match($this->sqlFilter, $sql)) {
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
