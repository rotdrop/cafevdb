<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\PageRenderer;

abstract class Renderer implements IRenderer
{
  /** @var string */
  protected $template;

  /*
   * Show the underlying template page. This is supposed to echo html
   * code to stdout. This is the default do-nothing implementation.
   *
   * @param bool $execute Kind of dry-run if set to false.
   */
  public function render(bool $execute = true)
  {
    return '';
  }

  public function cssPrefix():string
  {
    return 'cafevdb-page';
  }

  public function cssClass():string
  {
    return $this->template;
  }

  public function needPhpSession():bool
  {
    return false;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
