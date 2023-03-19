<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event as ORMEvent;

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\IUndoable;
use OCA\CAFEVDB\Common\GenericUndoable;

/**
 * An entity listener. The task is to remove orphaned files
 * entities. "orphaned" means they are no longer referenced from
 * DatabaseStorageFile entities.
 *
 * Files can be orphaned by update and remove operations.
 */
class DatabaseStorageFileEntityListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var IL10N */
  protected $l;

  /** @var array */
  protected $lock = [];

  /** @var IUndoable */
  protected $preCommitAction;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ILogger $logger,
    IL10N $l10n,
    EntityManager $entityManager,
    IAppContainer $appContainer,
  ) {
    $this->l = $l10n;
    $this->logger = $logger;
    $this->entityManager = $entityManager;
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   */
  public function postUpdate(Entities\DatabaseStorageFile $dirEntry, ORMEvent\LifecycleEventArgs $eventArgs)
  {
    $this->cleanupOrphans();
  }

  /**
   * {@inheritdoc}
   */
  public function postRemove(Entities\DatabaseStorageFile $dirEntry, ORMEvent\LifecycleEventArgs $eventArgs)
  {
    $this->cleanupOrphans();
  }

  /**
   * Cleanup the list of orphans by actually removing the orphans.
   *
   * @return void
   */
  protected function cleanupOrphans():void
  {
    if (!empty($this->preCommitAction)) {
      return;
    }
    $this->preCommitAction = new GenericUndoable(
      function() {
        $orphans = Entities\DatabaseStorageFile::getOrphans();
        /** @var Entities\EncryptedFile $orphan */
        foreach ($orphans as $orphan) {
          if ($orphan->getNumberOfLinks() == 0) {
            $this->entityManager->remove($orphan);
          }
        }
        Entities\DatabaseStorageFile::clearOrphans();
        $this->flush();
        return $orphans;
      },
      function($orphans) {
        Entities\DatabaseStorageFile::restoreOrphans($orphans);
      }
    );
    $this->entityManager->registerPreCommitAction($this->preCommitAction);
  }
}
