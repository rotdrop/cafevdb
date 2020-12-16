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
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;

class ProjectEventsController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var ParameterService */
  private $parameterService;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function serviceSwitch($topic)
  {
    try {
      switch ($topic) {
        case 'dialog': // open
          $projectId = $this->parameterService['projectId'];
          $projectId = $this->parameterService['projectName'];
          $templateParameters = [
            'projectId' => $projectId,
            'projectName' => $projectName,
            'cssClass' => 'projectevevents',
            // $tmpl->assign('Events', $events);
            // $tmpl->assign('EventMatrix', $eventMatrix);
            // $tmpl->assign('locale', $locale);
            // $tmpl->assign('timezone', Util::getTimezone());
            // $tmpl->assign('Selected', $selected);
          ];
          $response = new TemplateResponse(
            $this->appName(),
            'events',
            $templateParameters,
            'blank'
          );

          $response->addHeader('X-'.$this->appName().'-project-id', $projectId);
          $response->addHeader('X-'.$this->appName().'-project-name', $projectName);

          return $response;
        case 'delete':
        case 'detach':
        case 'redisplay':
        case 'select':
        case 'deselect':
        case 'download':
        case 'email':
          break;
        default:
          break;
      }
      return self::grumble($this->l->t('Unknown Request'));
    } catch (\Throwable $t) {
      return self::grumble($this->exceptionChainData($t));
    }
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
