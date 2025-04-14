<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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
/**
 * @file Expose tooltips as AJAY controllers, fetching them by their key.
 */

namespace OCA\CAFEVDB\Controller;

use Throwable;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception as DBALException;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Exceptions;

/** Fetch one or multiple tooltip via AJAX. */
class WebBrowserHistoryController extends Controller
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;

  public const GET_REQUEST_ALL = 'all';
  public const GET_REQUEST_TIMESTAMPS = 'timestamps';

  public const GET_MODE_SHALLOW = 'shallow';
  public const GET_MODE_DEEP = 'deep';
  public const GET_MODES = [
    self::GET_MODE_DEEP,
    self::GET_MODE_SHALLOW,
  ];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IRequest $request,
    protected EntityManager $entityManager,
    protected IDateTimeFormatter $dateTimeFormatter,
    protected IL10N $l,
    protected ILogger $logger,
    protected string $userId,
  ) {
    parent::__construct($appName, $request);
  }
  // phpcs:enable

  /**
   * Convert the given entry to a flat array for exporting.
   *
   * @param Entities\WebBrowserHistoryEntry $entry
   *
   * @param string $mode If self::GET_MODE_SHALLOW do not populate the post
   * data array.
   *
   * @return array
   */
  private function exportHistoryEntry(Entities\WebBrowserHistoryEntry $entry, string $mode):array
  {
    $data = [
      'key' => $entry->getKey(),
      'path' => $entry->getPath(),
      'hash' => $entry->getDataHash(),
    ];
    if ($mode === self::GET_MODE_DEEP) {
      $data['post'] = $entry->getData()->getData();
    }
    return $data;
  }

  /**
   * Prepare a database entry for export to the frontend.
   *
   * @param Entities\WebBrowserHistoryState $historyState
   *
   * @param string $modeOrKey If not in self::GET_MODES then export just the
   * single history entry with the given key, including the corresponding
   * post-data. If equal to self::GET_MODE_DEEP include also the request
   * parameters (post-data), otherwise omit theem.
   *
   * @return array Data in the form received by the put request.
   *
   * @throws Exceptions\EnduserNotificationException
   */
  private function exportHistoryState(
    Entities\WebBrowserHistoryState $historyState,
    string $modeOrKey,
  ):array {
    switch ($modeOrKey) {
      case self::GET_MODE_DEEP:
      case self::GET_MODE_SHALLOW:
        $dataItem = [
          'modificationTime' => $historyState->getCreated()->format('U.v'),
          'position' => $historyState->getPos()->getKey(),
          'history' => [],
        ];
        if ($modeOrKey === self::GET_MODE_DEEP) {
          $dataItem['requestData'] = [];
        }
        /** @var Entities\WebBrowserHistoryEntry $entry */
        foreach ($historyState->getStack() as $key => $entry) {
          $hash = $entry->getData()->getHash();
          $dataItem['history'][$key] = $this->exportHistoryEntry($entry, $modeOrKey);
          if ($modeOrKey === self::GET_MODE_DEEP) {
            $dataItem['requestData'][$hash] = $entry->getData()->getData();
          }
        }
        break;
      default:
        // just the data form a single item, this is always "deep" including the post data.
        $entry = $historyState->getEntry($modeOrKey);
        if ($entry === null) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('History record with key "%1$s" at time "%2$s" for user "%3$s" could not be found.', [
              $modeOrKey,
              $this->dateTimeFormatter->formatDateTime($historyState->getCreated()),
              $this->userId,
            ]),
            0,
            httpStatusCode: Http::STATUS_NOT_FOUND,
          );
        }
        $dataItem = $this->exportHistoryEntry($entry, self::GET_MODE_DEEP);
        break;
    }
    return $dataItem;
  }

  /**
   * @param string|float $timestamp
   *
   * @param string $modeOrKey
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function get(string|float $timestamp, string $modeOrKey = self::GET_MODE_SHALLOW)
  {
    $repository = $this->getDatabaseRepository(Entities\WebBrowserHistoryState::class);
    switch ($timestamp) {
      case self::GET_REQUEST_ALL:
        $historyStates = $repository->findBy(
          criteria: [ 'userId' => $this->userId ],
          orderBy: [ 'created' => 'DESC' ],
        );
        $data = [];
        /** @var Entities\WebBrowserHistoryState $historyState */
        foreach ($historyStates as $historyState) {
          $item = $this->exportHistoryState($historyState, $modeOrKey);
          $data[$item['modificationTime']] = $item;
        }
        break;
      case self::GET_REQUEST_TIMESTAMPS:
        $historyStates = $repository->findBy(
          criteria: [ 'userId' => $this->userId ],
          orderBy: [ 'created' => 'DESC' ],
        );
        $data = [];
        foreach ($historyStates as $historyState) {
          // return fractional milliseconds as this is what the frontend uses.
          $data[] = $historyState->getCreated()->format('U.v');
        }
        break;
      default:
        $date = self::convertToDateTime($timestamp);
        $historyState = $repository->findOneBy([ 'userId' => $this->userId, 'created' => $date ]);
        if (empty($historyState)) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('History record at time "%1$s" for user "%2$s" could not be found.', [
              $this->dateTimeFormatter->formatDateTime($date),
              $this->userId,
            ]),
            0,
            httpStatusCode: Http::STATUS_NOT_FOUND,
          );
        }
        $data = $this->exportHistoryState($historyState, $modeOrKey);
        break;
    }
    return self::dataResponse($data);
  }

  /**
   * @param float $timestamp
   *
   * @param string $position
   *
   * @param array $history
   *
   * @param array $requestData
   *
   * @return DataResponse
   *
   * @throws Exceptions\DatabaseException
   *
   * @NoAdminRequired
   */
  public function put(float $timestamp, string $position, array $history, array $requestData):DataResponse
  {
    $historyState = new Entities\WebBrowserHistoryState($timestamp, $this->userId);
    $this->entityManager->beginTransaction();
    try {
      $this->persist($historyState);
      $this->flush(); // we need the autoincrement value

      $dataRepository = $this->getDatabaseRepository(Entities\WebBrowserHistoryData::class);
      $data = [];
      foreach ($requestData as $hash => $postData) {
        $dataItem = $dataRepository->find($hash);
        if (!$dataItem) {
          $dataItem = new Entities\WebBrowserHistoryData($hash, $postData);
          $this->persist($dataItem);
        }
        $data[$hash] = $dataItem;
      }

      foreach ($history as $key => $entryData) {
        $entry = new Entities\WebBrowserHistoryEntry(
          state: $historyState,
          key: $key,
          path: $entryData['path'],
          data: $data[$entryData['hash']],
        );
        $this->persist($entry);
        $historyState->addEntry($entry);
      }

      $historyState->setPos($historyState->getEntry($position));

      $this->entityManager->flush();
      $this->entityManager->commit();
    } catch (Throwable $t) {
      $this->entityManager->rollback();

      if ($t instanceof DBALException\UniqueConstraintViolationException) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('History record at time "%1$s" for user "%2$s" already has been saved previously.', [
            $this->dateTimeFormatter->formatDateTime($historyState->getCreated()),
            $this->userId,
          ]),
          0,
          $t,
          httpStatusCode: Http::STATUS_CONFLICT,
        );
      }

      throw new Exceptions\DatabaseException(
        $this->l->t('Unable to store history data.'),
        0,
        $t
      );
    }
    return self::dataResponse([
      'message' => $this->l->t('History record at time "%1$s" for user "%2$s" has been saved into the database.', [
        $this->dateTimeFormatter->formatDateTime($historyState->getCreated()),
        $this->userId,
      ]),
    ]);
  }

  /**
   * @param float $timestamp
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function delete(float $timestamp):DataResponse
  {
    /** @var OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository $repository */
    $repository = $this->getDatabaseRepository(Entities\WebBrowserHistoryState::class);
    $date = self::convertToDateTime($timestamp);
    $historyState = $repository->findOneBy([ 'userId' => $this->userId, 'created' => $date ]);
    if (empty($historyState)) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('History record at time "%1$s" for user "%2$s" could not be found.', [
          $this->dateTimeFormatter->formatDateTime($date),
          $this->userId,
        ]),
        0,
        httpStatusCode: Http::STATUS_NOT_FOUND,
      );
    }
    // ok, the structure is seemingly a little bit too complex
    $this->entityManager->beginTransaction();
    try {
      $historyState->setPos(null);
      $this->entityManager->flush();
      foreach ($historyState->getStack() as $entry) {
        $this->entityManager->flush();
        $entry->getData()->removeFromEntry($entry);
      }
      $this->entityManager->flush();
      $this->remove($historyState, flush: false);
      $this->entityManager->flush();
      $this->entityManager->commit();
    } catch (Throwable $t) {
      $this->entityManager->rollback();
      throw new Exceptions\DatabaseException(
        $this->l->t('Unable delete history state at time %1$s of user "%2$s".', [
          $this->dateTimeFormatter->formatDateTime($historyState->getCreated()),
          $this->userId,
        ]),
        0,
        $t
      );
    }
    return self::dataResponse([]);
  }
}
