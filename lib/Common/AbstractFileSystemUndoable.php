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

namespace OCA\CAFEVDB\Common;

use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Storage\UserStorage;

/** Base for file-system related IUndoable implementations. */
abstract class AbstractFileSystemUndoable extends AbstractUndoable
{
  use \OCA\RotDrop\Toolkit\Traits\LoggerTrait;

  /** @var IL10N */
  protected $l;

  /** @var ITimeFactory */
  protected $timeFactory;

  /** @var UserStorage */
  protected $userStorage;

  /** {@inheritdoc} */
  public function initialize(IAppContainer $appContainer):void
  {
    parent::initialize($appContainer);
    $this->userStorage = $this->appContainer->get(UserStorage::class);
    $this->timeFactory = $this->appContainer->get(ITimeFactory::class);
    $this->l = $this->appContainer->get(IL10N::class);
    $this->logger = $this->appContainer->get(ILogger::class);
  }

  /**
   * Remove multipled slashes.
   *
   * @param string $path
   *
   * @return string
   */
  protected static function normalizePath(string $path):string
  {
    return rtrim(
      preg_replace('|'.UserStorage::PATH_SEP.'+|', UserStorage::PATH_SEP, $path),
      UserStorage::PATH_SEP);
  }

  /**
   * Create a new unique name for renaming.
   *
   * @param string $path
   *
   * @return string
   */
  protected function renamedName(string $path):string
  {
    $time = $this->timeFactory->getTime();
    $pathInfo = pathinfo($path);
    $renamed = $pathInfo['dirname']
      . UserStorage::PATH_SEP
      . $pathInfo['filename'] . '-renamed-' . $time . '.' . $pathInfo['extension'];
    return $renamed;
  }
}
