<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Traits;

use OCP\IInitialStateService;
use OCP\IUser;

use OCA\CAFEVDB\Common\Config;

trait InitialStateTrait {

    /** @var IInitialStateService */
    protected $initialStateService;

    protected function publishInitialStateForUser(IUser $user) {
        $this->initialStateService->provideInitialState(
            Config::APP_NAME,
            'CAFEVDB',
            [
                'blah' => 'blub',
                'foo' => [ 'bar' => 13 ],
            ]);
        $this->initialStateService->provideInitialState(
            Config::APP_NAME,
            'PHPMYEDIT',
            [
                'blah' => 'blub',
                'foo' => [ 'bar' => 13 ],
            ]);
    }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
