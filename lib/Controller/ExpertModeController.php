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
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ToolTipsService;

class ExpertModeController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const ERROR_TEMPLATE = "errorpage";
  const TEMPLATE = "expertmode";

  /** @var IL10N */
  private $l;

  /** @var ToolTipsService */
  private $toolTipsService;

  public function __construct(
    $appName,
    IRequest $request,
    ConfigService $configService,
    ToolTipsService $toolTipsService
  ) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->toolTipsService = $toolTipsService;
    $this->l = $this->l10N();
  }

  /**
   * Return settings form
   *
   * @NoAdminRequired
   */
  public function form() {
    if (!$this->inGroup()) {
      return new TemplateResponse(
        $this->appName(),
        self::ERROR_TEMPLATE,
        [
          'error' => 'notamember',
          'userId' => $this->userId(),
        ],
      'blank');
    };

    // may restrict this to the group admins

    $templateParameters = [
      'appName' => $this->appName(),
      'expertmode' => $this->getUserValue('expertmode', 'off'),
      'showToolTips' => $this->getUserValue('tooltips', 'on'),
      'toolTips' => $this->toolTipsService,
    ];
    $links = ['phpmyadmin',
              'phpmyadminoc',
              'sourcecode',
              'sourcedocs',
              'nextclouddev'];
    foreach ($links as $link) {
      $templateParameters[$link] = $this->getConfigValue($link);
    }

    return new TemplateResponse(
      $this->appName(),
      self::TEMPLATE,
      $templateParameters,
      'blank',
    );
  }

  /**
   * Return settings form
   *
   * @NoAdminRequired
   */
  public function action($operation, $data) {
    switch ($operation) {
    case 'setupdb':
    case 'makeviews':
    case 'syncevents':
    case 'wikiprojecttoc':
    case 'attachwebpages':
    case 'sanitizephones':
    case 'geodata':
    case 'uuid':
    case 'imagemeta':
      return self::grumble($this->l->t('TO BE IMPLEMENTED'));
    case 'example':
      return self::response($this->l->t('Hello World!'));
    case 'clearoutput':
      return self::response($this->l->t('empty'));
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
