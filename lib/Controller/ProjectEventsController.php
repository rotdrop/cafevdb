<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\ToolTipsService;

class ProjectEventsController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var \OCA\CAFEVDB\Service\ParameterService */
  private $parameterService;

  /** @var \OCA\CAFEVDB\Service\EventsService */
  private $eventsService;

  /** @var \OCA\CAFEVDB\Service\CalDavService */
  private $calDavService;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , EventsService $eventsService
    , CalDavService $calDavService
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->eventsService = $eventsService;
    $this->calDavService = $calDavService;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function serviceSwitch($topic)
  {
    try {
      $projectId = $this->parameterService['projectId'];
      $projectName = $this->parameterService['projectName'];

      if (empty($projectId) || empty($projectName)) {
        return self::grumble(
          $this->l->t('Project-id AND -name have to be specified (%s / %s)',
                      [ empty($projectId) ? '?' : $projectId,
                        empty($projectName) ? '?' : $projectName ]));
      }

      $selectedEvents = $this->parameterService->getParam('eventSelect', []);
      $selected = []; // array marking selected events

      foreach ($selectedEvents as $eventUri) {
        $selected[$eventUri] = true;
      }

      switch ($topic) {
        case 'dialog': // open
          $template = 'events';
          $events = $this->eventsService->events($projectId);
          break;
        case 'redisplay':
          $template = 'eventslisting';
          $events = $this->eventsService->events($projectId);
          break;
        case 'select':
          $template = 'eventslisting';
          $events = $this->eventsService->events($projectId);
          $selected = []; // array marking selected events
          foreach ($events as $event) {
            $selected[$event['uri']] = true;
          }
          break;
        case 'deselect':
          $template = 'eventslisting';
          $events = $this->eventsService->events($projectId);
          $selected = []; // array marking selected events
          break;
        case 'delete':
          $template = 'eventslisting';
          $eventUri = $this->parameterService['EventURI'];
          $calendarId = $this->parameterService['CalendarId'][$eventUri];
          $this->calDavService->deleteCalendarObject($calendarId, $eventUri);
          $this->eventsService->unregister($projectId, $eventUri);
          $events = $this->eventsService->events($projectId);
          unset($selected[$eventUri]);
          break;
        case 'detach':
          $template = 'eventslisting';
          $eventUri = $this->parameterService['EventURI'];
          $this->eventsService->unchain($projectId, $eventUri);
          unset($selected[$eventUri]);
          break;
        case 'download':
          $calendarIds = $this->parameterService['CalendarId'];

          if (count($selected) > 0) {
            $exports = [];
            foreach ($selected as $eventUri => $select) {
              $exports[$eventUri] = $calendarIds[$eventUri];
            }
          } else {
            $exports = $calendarIds;
          }

          $fileName = $projectName.'-'.$this->timeStamp().'.ics';

          return $this->dataDownloadResponse(
            $this->eventsService->exportEvents($exports, $projectName),
            $fileName,
            'text/calendar');

        case 'email':
        default:
          return self::grumble($this->l->t('Unknown Request'));
      }

      $dfltIds = $this->eventsService->defaultCalendars();
      $eventMatrix = $this->eventsService->eventMatrix($events, $dfltIds);

      $templateParameters = [
        'projectId' => $projectId,
        'projectName' => $projectName,
        'cssClass' => 'projectevents',
        'locale' => $this->getLocale(),
        'timezone' => $this->getTimeZone(),
        'events' => $events,
        'eventMatrix' => $eventMatrix,
        'selected' => $selected,
        'eventsService' => $this->eventsService,
        'toolTips' => $this->di(ToolTipsService::class),
        'requesttoken' => \OCP\Util::callRegister(),
      ];
      $response = new TemplateResponse(
        $this->appName(),
        $template,
        $templateParameters,
        'blank'
      );

      $response->addHeader('X-'.$this->appName().'-project-id', $projectId);
      $response->addHeader('X-'.$this->appName().'-project-name', $projectName);

      return $response;

    } catch (\Throwable $t) {
      return self::grumble($this->exceptionChainData($t));
    }
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
