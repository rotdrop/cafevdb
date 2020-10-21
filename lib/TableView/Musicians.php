<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\TableView;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Legacy\PME\PHPMyEdit;

/**Table generator for Musicians table. */
class Musicians extends PMETableViewBase
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public function __construct(
    ConfigService $configService,
    PHPMyEdit $phpMyEdit
  ) {
    parent::__construct($configService, $phpMyEdit);
    $this->logInfo(__METHOD__.": Hello World!");
  }

  /** Short title for heading. */
  public function shortTitle() {
    return "Unimplemented";
  }


  /** Header text informations. */
  public function headerText() {
    return "Unimplemented";
  }

  /** Show the underlying table. */
  public function render(){
    return "Unimplemented";
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
