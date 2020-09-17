<?php
/**
 * Nextcloud - cafevdb
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2014-2020
 */

namespace OCA\CAFEVDB\Service;

use OCP\IL10N;

class HistoryService
{
  const MAX_HISTORY_SIZE = 100;
  const SESSION_HISTORY_KEY = 'PageHistory';
  const PME_ERROR_READONLY = 1;

  private $l;

  /**The data-contents. A "cooked" array structure with the
   * following components:
   *
   * array('size' => NUMBER_OF_HISTORY_RECORDS,
   *       'position' => CURRENT_POSITION_INTO_HISTORY_RECORDS,
   *       'records' => array(# => clone of $_POST));
   */
  private $session;
  private $historyRecords;
  private $historyPosition;
  private $historySize;

  /**Fetch any existing history from the session or initialize an
   * empty history if no history record is found.
   */
  public function __construct(SessionService $session, IL10N $l) {
    $this->session = $session;
    $this->l = $l;
    $this->loadHistory();
  }

  /**Initialize a sane do-nothing record. */
  private function defaultHistory()
  {
    $this->historySize = 1;
    $this->historyPosition = 0;
    $this->historyRecords = array(array('md5' => md5(serialize(array())),
                                        'data' => array()));
  }

  /**Add a history snapshot. */
  public function pushHistory($data)
  {
    ksort($data);
    $md5 = md5(serialize($data));
    $historyData = $this->historyRecords[$this->historyPosition];
    if ($historyData['md5'] != $md5) {
      // add the new record if it appears to be new
      array_splice($this->historyRecords, 0, $this->historyPosition);
      array_unshift($this->historyRecords, array('md5' => $md5,
                                                 'data' => $data));
      $this->historyPosition = 0;
      $this->historySize = count($this->historyRecords);
      while ($this->historySize > self::MAX_HISTORY_SIZE) {
        array_pop($this->historyRecords);
        --$this->historySize;
      }
    }
  }

  /**Fetch the history record at $offset. The function will through
   * an exception if offset is out of bounds.
   */
  public function fetchHistory($offset)
  {
    $newPosition = $this->historyPosition + $offset;
    if ($newPosition >= $this->historySize || $newPosition < 0) {
      throw new \OutOfBoundsException(
        $this->l->t('Invalid history position %d request, history size is %d',
                    array($newPosition, $this->historySize)));
    }

    $this->historyPosition = $newPosition;

    // Could check for valid data here, but so what
    return $this->historyRecords[$this->historyPosition]['data'];
  }

  /**Return the current position into the history. */
  public function historyPosition()
  {
    return $this->historyPosition;
  }

  /**Return the current position into the history. */
  public function historySize()
  {
    return $this->historySize;
  }

  /**Return true if the recorded history is essentially empty.
   */
  public function historyEmpty()
  {
    return $this->historySize <= 1 && count($this->historyRecords[0]['data']) == 0;
  }

  /**Store the current state whereever. Currently the PHP session
   * data, but this is not guaranteed.
   */
  public function storeHistory()
  {
    $storageValue = array('size' => $this->historySize,
                          'position' => $this->historyPosition,
                          'records' => $this->historyRecords);
    $this->session->storeValue(self::SESSION_HISTORY_KEY, $storageValue);
  }

  /**Load the history state. Initialize to default state in case of
   * errors.
   */
  private function loadHistory()
  {
    $loadValue = $this->session->retrieveValue(self::SESSION_HISTORY_KEY);
    if (!$this->validateHistory($loadValue)) {
      $this->defaultHistory();
      return false;
    }
    $this->historySize = $loadValue['size'];
    $this->historyPosition = $loadValue['position'];
    $this->historyRecords = $loadValue['records'];
    return true;
  }

  /**Validate the given history records, return false on error.
   */
  private function validateHistory($history)
  {
    if ($history === false ||
        !isset($history['size']) ||
        !isset($history['position']) ||
        !isset($history['records']) ||
        !$this->validateHistoryRecords($history)) {
      return false;
    }
    return true;
  }

  /**Validate one history entry */
  private function validateHistoryRecord($record) {
    if (!is_array($record)) {
      return false;
    }
    if (!isset($record['md5']) || $record['md5'] != md5(serialize($record['data']))) {
      return false;
    }
    return true;
  }

  /**Validate all history records. */
  private function validateHistoryRecords($history) {
    foreach($history['records'] as $record) {
      if (!$this->validateHistoryRecord($record)) {
        return false;
      }
    }
    return true;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
