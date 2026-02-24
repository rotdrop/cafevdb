<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024-2026 Claus-Justus Heine
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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http;
use OCP\IRequest;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\FuzzyInputService;

/**
 * General data validation controller.
 *
 * @todo This controller almost has no code in it, check whether it is needed
 * or move more validation code here. It is actually used in
 * project-participants-fields.ts.
 */
#[TSAttributes\TypeScript]
class ValidationController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public const END_POINT_VALIDATE_GENERAL = 'validate/general';
  public const TOPIC_MONETARY_VALUE = 'monetary-value';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    private FuzzyInputService $fuzzyInput,
    protected ConfigService $configService,
  ) {
    parent::__construct($appName, $request);
    $this->l = $this->l10N();
  }
  // phpcs:enable

  /**
   * @param string $topic
   *
   * @param string $value
   *
   * @return DataResponse
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'POST', url: '/' . self::END_POINT_VALIDATE_GENERAL . '/{topic}')]
  public function serviceSwitch(string $topic, string $value): Http\DataResponse|Http\JSONResponse
  {
    switch ($topic) {
      case self::TOPIC_MONETARY_VALUE:
        $value = Util::normalizeSpaces($value);
        $amount = 0;
        if (!empty($value)) {
          $amount = $this->fuzzyInput->currencyValue($value);
          if ($amount === false) {
            throw new Exceptions\EnduserNotificationException(
              $this->l->t('Could not parse number: "%s"', [ $value ]),
            );
          }
        }
        return DTO\AmountResponse::fromArray(compact('amount'))->response();
    }
    throw new Exceptions\EnduserNotificationException($this->l->t('Unknown Request'));
  }
}
