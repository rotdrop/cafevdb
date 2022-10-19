<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\ILogger;

use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Listen to renamed and deleted events in order to keep the
 * configured document-templates synchronized with the cloud
 * file-system.
 */
class SepaBulkTransactionSubmittedListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = Events\PostChangeSepaBulkTransactionSubmitDate::class;

  /** @var IAppContainer */
  private $appContainer;

  /**
   * @param IAppContainer $appContainer App-container in order to have a leight-weight constructor.
   */
  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    if (get_class($event) !== self::EVENT) {
      return;
    }
    /** @var Events\PostChangeSepaBulkTransactionSubmitDate $event */

    /** @var SepaBulkTransactionService $bulkTransactionService */
    $bulkTransactionService = $this->appContainer->get(SepaBulkTransactionService::class);
    $bulkTransaction = $event->getEntity();
    $submitDate = $event->getNewValue();

    // well register pre-commit actions as appropriate
    $bulkTransactionService->markBulkTransactionSubmitted($bulkTransaction, $submitDate);
  }
}
