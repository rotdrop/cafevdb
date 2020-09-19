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

use OCA\CAFEVDB\Service\ConfigService;

class PersonalSettingsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var IL10N */
  private $l;

  //@@TODO inject config via constructor
  public function __construct($appName, IRequest $request, ConfigService $configService) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->l = $this->l10N();
  }

  public function set($parameter, $value) {
    $status = Http::STATUS_OK;
    switch ($parameter) {
    case 'tooltips':
      $tooltips = filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
      if ($tooltips === null) {
        return self::grumble($this->l->t('Value "%1$s" for set tooltips is not convertible to boolean', [$value]));
      }
      return self::response($this->l->t('Switching tooltips %1$s', [$tooltips ? 'on' : 'off']));
    default:
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

  static private function response($message, $status = Http::STATUS_OK)
  {
    return new DataResponse(['message' => $message], $status);
  }

  static private function grumble($message)
  {
    return self::response($message, Http::STATUS_BAD_REQUEST);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
