<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license GNU AGPL version 3 or any later version
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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\IRequest;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\FuzzyInputService;

use OCA\CAFEVDB\Common\Util;

class ValidationController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var \OCA\CAFEVDB\Service\ParametereService */
  private $parameterService;

  /** @var \OCA\CAFEVDB\Service\FuzzyInputService */
  private $fuzzyInput;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , FuzzyInputService $fuzzyInput
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->fuzzyInput = $fuzzyInput;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function serviceSwitch($topic, $value)
  {
    switch ($topic) {
    case 'monetary_value':
    case 'monetary-value':
      $value = Util::normalizeSpaces($value);
      $amount = 0;
      if (!empty($value)) {
        $amount = $this->fuzzyInput->currencyValue($value);
        if ($amount === false) {
          return self::grumble($this->l->t('Could not parse number: "%s"', [ $value ]));
        }
      }
      return self::dataResponse([ 'amount' => $amount ]);
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
