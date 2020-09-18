<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;

class AdminSettingsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var IL10N */
  private $l;

  //@@TODO inject config via constructor
  public function __construct($appName, IRequest $request, IConfig $containerConfig, IL10N $l) {
    parent::__construct($appName, $request);

    $this->containerConfig = $containerConfig;
    $this->l = $l;
  }

  public function save($orchestraUserGroup) {
    if (!empty($orchestraUserGroup)) {
      $this->setAppValue('usergroup', $orchestraUserGroup);
      return new DataResponse(
        ['message' => $this->l->t('Setting orchestra group to `%s\'. Please login as group administrator and configure the Camerata DB application.', [$orchestraUserGroup])]
      );
    } else {
      return new DataResponse(
        ['message' => $this->l->t('Refusing to set the orchestra group to an empty string')],
        Http::STATUS_BAD_REQUEST
      );
    }
    return true;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
