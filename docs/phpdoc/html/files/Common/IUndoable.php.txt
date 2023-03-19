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

namespace OCA\CAFEVDB\Common;

use OCP\AppFramework\IAppContainer;

/**
 * Simplistic do-undo interface in order to be stacked into a
 * do-undo-list.
 *
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
interface IUndoable
{
  /**
   * Do something.
   *
   * @return void
   */
  public function do():void;

  /**
   * Undo that what was done by do().
   *
   * @return void
   */
  public function undo():void;

  /**
   * Reset initial state.
   *
   * @return void
   */
  public function reset():void;

  /**
   * Lazy initialization in order to have a more lightweight constructor for
   * the actions and to inject the app-container if it is needed.
   *
   * @param IAppContainer $appContainer
   *
   * @return void
   */
  public function initialize(IAppContainer $appContainer):void;
}
