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

use Psr\Log\LoggerInterface as ILogger;

use OCP\IL10N;
use OCP\ISession;
use OCP\IRequest;

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

  const HASH_KEY = '__post_data_hash__';
  const FRONTEND_URL_PATH_KEY = '__frontend_url_path__';
  const SESSION_HISTORY_KEY = 'PageHistory';
  const SESSION_LAST_URL_PATH_KEY = 'LastUrlPath';

  /**
   * @var array
   *
   * Top-level excluded keys which we do not want to cache.
   */
  private const EXCLUDE_KEYS = [
    self::HASH_KEY,
    self::FRONTEND_URL_PATH_KEY,
    '_route',
    'renderAs',
    'template',
    'projectId',
    'projectName',
  ];

  /** @var bool */
  protected $debug = false;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private IRequest $request,
    protected IL10N $l,
    protected ILogger $logger,
    protected ISession $session,
    protected string $appName,
  ) {
    if (!$this->sessionKeyExists(self::SESSION_HISTORY_KEY)) {
      $this->logInfo('NO SESSION HISTORY DATA PRESENT, INITIALIZE');
      $this->sessionStoreValue(self::SESSION_HISTORY_KEY, []);
    } else {
      $this->logDebug('SESSION HISTORY DATA ' . print_r($this->sessionRetrieveValue(self::SESSION_HISTORY_KEY), true));
    }
  }
  // phpcs:enable

  /**
   * Save a history snapshot. If $key is given use its value as key. Otherwise
   * if $data[self::HASH_KEY] is present use it as key. Otherwise do nothing.
   * Save a history snapshot
   *
   * @param null|array $data Data to store. If null
   * IRequest::getParams() is used.
   *
   * @param null|string $key
   *
   * @return void
   */
  public function save(?array $data = null, ?string $key = null):void
  {
    if ($data === null) {
      $data = $this->request->getParams();
    }
    $hash = $key ?? ($data[self::HASH_KEY] ?? null);
    if (!$hash) {
      $this->logError('NO HASH KEY');
      return;
    }
    $this->set($hash, $data);
  }

  /**
   * Add a history snapshot. If $key is given use its value as key. Otherwise
   * if $data[self::HASH_KEY] is present use it as key. Otherwise do nothing.
   *
   * @param string $hash
   *
   * @param array $data
   *
   * @return void
   */
  public function set(string $hash, array $data):void
  {
    $urlPath = $data[self::FRONTEND_URL_PATH_KEY];
    foreach (self::EXCLUDE_KEYS as $key) {
      unset($data[$key]);
    }
    if (!empty($data)) {
      $historyData = $this->sessionRetrieveValue(self::SESSION_HISTORY_KEY);
      if (empty($historyData[$hash])) {
        $historyData[$hash] = $data;
        $this->sessionStoreValue(self::SESSION_HISTORY_KEY, $historyData);
      }
    }
    if (empty($urlPath) || $urlPath === '/') {
      $urlPath = null;
    }
    $this->sessionStoreValue(self::SESSION_LAST_URL_PATH_KEY, $urlPath);
  }

  /**
   * Retrieve previously stored request data from the PHP session.
   *
   * @param string $hash
   *
   * @return null|array
   */
  public function get(string $hash):?array
  {
    $data = $this->sessionRetrieveValue(self::SESSION_HISTORY_KEY)[$hash] ?? null;
    if ($data) {
      $data = array_filter($data, fn($value, $key) => !in_array($key, self::EXCLUDE_KEYS), ARRAY_FILTER_USE_BOTH);
    }
    return empty($data) ? null : $data;
  }

  /**
   * Fetch the most recent frontend url path from the session, if it exists.
   *
   * @return null|string
   */
  public function getLastUrlPath():?string
  {
    $urlPath = $this->sessionRetrieveValue(self::SESSION_LAST_URL_PATH_KEY, null);
    return $urlPath === '/' ? null : $urlPath;
  }
}
