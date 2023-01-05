<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2022 Claus-Justus Heine
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
use OCP\AppFramework\Http\DataResponse;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\FuzzyInputService;

use OCA\CAFEVDB\Common\Util;

/**
 * General data validation controller.
 *
 * @todo This controller almost has no code in it, check whether it is needed
 * or move more validation code here.
 */
class ValidationController extends Controller
{
  use \OCA\RotDrop\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var \OCA\CAFEVDB\Service\ParametereService */
  private $parameterService;

  /** @var \OCA\CAFEVDB\Service\FuzzyInputService */
  private $fuzzyInput;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    RequestParameterService $parameterService,
    ConfigService $configService,
    FuzzyInputService $fuzzyInput,
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->fuzzyInput = $fuzzyInput;
    $this->l = $this->l10N();
  }
  // phpcs:enable

  /**
   * @param string $topic
   *
   * @param string $value
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function serviceSwitch(string $topic, string $value):DataResponse
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
