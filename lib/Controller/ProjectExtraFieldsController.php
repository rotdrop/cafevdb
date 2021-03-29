<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\ProjectExtraFields as Renderer;
use OCA\CAFEVDB\Service\ProjectExtraFieldsService;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

class ProjectExtraFieldsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit */
  protected $pme;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var \OCA\CAFEVDB\Service\FuzzyInputService */
  private $fuzzyInput;

  /** @var \OCA\CAFEVDB\Service\ProjectExtraFieldsService */
  private $extraFieldsService;

  /** @var EntityManager */
  protected $entityManager;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , EntityManager $entityManager
    , Renderer $renderer
    , PHPMyEdit $phpMyEdit
    , FuzzyInputService $fuzzyInput
    , ProjectExtraFieldsService $extraFieldsService
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->renderer = $renderer;
    $this->pme = $phpMyEdit;
    $this->fuzzyInput = $fuzzyInput;
    $this->extraFieldsService = $extraFieldsService;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function serviceSwitch($topic, $value = null)
  {
    $projectValues = $this->parameterService->getPrefixParams($this->pme->cgiDataName());
    switch ($topic) {
      case 'allowed-values-option':
        if (!isset($value['selected']) ||
            !isset($value['data']) ||
            !isset($value['keys'])) {
          return self::grumble($this->l->t('Missing parameters in request %s', $topic));
        }
        $selected = $value['selected'];
        $data  = $value['data'];
        $keys  = $value['keys'] ? $value['keys'] : [];
        $index = $data['index'];
        $used  = $data['used'] === 'used';
        $allowed = $projectValues['allowed_values'];

        $allowed = array_values($allowed);

        // sanitize and potentially add missing keys
        $allowed = $this->extraFieldsService->explodeAllowedValues(
          $this->extraFieldsService->implodeAllowedValues($allowed),
          false);

        if (count($allowed) !== 1) {
          return self::grumble($this->l->t('No or too many items available: %s',
                                           print_r($allowed, true) ));
        }

        $item = $allowed[0];

        // remove dangerous html
        $item['tooltip'] = $this->fuzzyInput->purifyHTML($item['tooltip']);

        switch ($data['data-type']) {
        case 'deposit':
        case 'service-fee':
        case 'money':
          // see that it is a valid decimal number ...
          if (!empty($item['data'])) {
            $parsed = $this->fuzzyInput->currencyValue($item['data']);
            if ($parsed === false) {
              return self::grumble($this->l->t('Could not parse number: "%s"', [ $item['data'] ]));
            }
            $item['data'] = $parsed;
          }
          break;
        default:
          break;
        }

        $input = '';
        $options = [];
        if (!empty($item['key'])) {
          $key = $item['key'];
          $options[] = [ 'name' => $item['label'],
                         'value' => $key,
                         'flags' => ($selected === $key ? PageNavigation::SELECTED : 0) ];
          $input = $this->renderer->allowedValueInputRow($item, $index, $used);
        }
        $options = PageNavigation::selectOptions($options);

        return self::dataResponse([
          'message' => $this->l->t("Request \"%s\" successful", $topic),
          'AllowedValue' => $allowed,
          'AllowedValueInput' => $input,
          'AllowedValueOption' => $options,
        ]);
      default:
        break;
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
