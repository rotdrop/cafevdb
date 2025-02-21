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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;

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

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    protected EntityManager $entityManager,
    protected IL10N $l,
    protected ILogger $logger,
  ) {
    parent::__construct($appName, $request);
  }
  // phpcs:enable

  /**
   * @param int $timeStamp
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function get(int $timeStamp)
  {
    throw new Exceptions\Exception($this->l->t('Unimplemented'));
  }

  /**
   * @param int $timeStamp
   *
   * @param string $historyData JSON encoded history data.
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function put(int $timeStamp, string $historyData)
  {
    throw new Exceptions\Exception($this->l->t('Unimplemented'));
  }

  /**
   * @param int $timeStamp
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function delete(int $timeStamp)
  {
    throw new Exceptions\Exception($this->l->t('Unimplemented'));
  }
}
