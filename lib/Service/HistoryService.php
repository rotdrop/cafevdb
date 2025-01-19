<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2014-2025 Claus-Justus Heine
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

use OutOfBoundsException;

use OCP\ISession;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

/**
 * Page history via PHP session.
 *
 * @todo This now should be obsolete as the navigation history is handled by
 * the Javascript code.
 */
class HistoryService
{
  use \OCA\CAFEVDB\Traits\SessionTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const MAX_HISTORY_SIZE = 1;
  const SESSION_HISTORY_KEY = 'PageHistory';
  const PME_ERROR_READONLY = 1;

  /** @var bool */
  protected $debug = false;

  /**
   * @var array|null Clone of the most recent request. The purpose is to
   * restore the previous view acros browser reload/close, change of user agents.
   */
  private ?array $historyRecord;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private ISession $session,
    protected IL10N $l,
    protected ILogger $logger,
  ) {
    $this->load();
  }
  // phpcs:enable

  /**
   * Initialize a sane do-nothing record.
   *
   * @return void
   */
  private function default():void
  {
    $this->historyRecord = [
      'md5' => md5(serialize([])),
      'data' => []
    ];
  }

  /**
   * Add a history snapshot.
   *
   * @param array $data
   *
   * @return void
   */
  public function save(array $data):void
  {
    ksort($data);
    $md5 = md5(serialize($data));
    if ($this->historyRecord['md5'] != $md5) {
      // add the new record if it appears to be new
      $this->historyRecord['md5'] = $md5;
      $this->historyRecord['data'] = $data;
      if ($this->debug) {
        $this->printRecord();
      }
    }
  }

  /**
   * Debugging utility.
   *
   * @return void
   */
  private function printRecord():void
  {
    $printKeys = [ 'projectId', 'template' ];
    $printRecord = [];
    foreach ($printKeys as $key) {
      $printRecord[$key] = $this->historyRecord['data'][$key] ?? 'undefined';
    }
    $message .= ', '. print_r($printRecord, true);
    $this->logInfo($message, [], 2, true);
  }

  /**
   * Fetch the history record.
   *
   * @return array
   */
  public function fetch():array
  {
    if ($this->debug) {
      $this->printRecord();
    }

    // Could check for valid data here, but so what
    return $this->historyRecord['data'];
  }

  /**
   * Store the current state whereever. Currently the PHP session
   * data, but this is not guaranteed.
   *
   * @return void
   */
  public function store():void
  {
    $this->sessionStoreValue(self::SESSION_HISTORY_KEY, $this->historyRecord);
  }

  /**
   * Load the history state. Initialize to default state in case of
   * errors.
   *
   * @return bool
   */
  private function load():bool
  {
    $loadValue = $this->sessionRetrieveValue(self::SESSION_HISTORY_KEY);
    if (!$this->validate($loadValue)) {
      $this->default();
      return false;
    }
    $this->historyRecord = $loadValue;
    return true;
  }

  /**
   * Validate the given history record, return false on error.
   *
   * @param null|array $record
   *
   * @return bool
   */
  private function validate(?array $record):bool
  {
    if (!is_array($record)) {
      return false;
    }
    if (!isset($record['md5']) || $record['md5'] != md5(serialize($record['data']))) {
      return false;
    }
    return true;
  }
}
