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
use OCP\AppFramework\Http\DataDownloadResponse;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\CalDavService;

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

      $selectedEvents = $this->parameterService->getParam('EventSelect', []);
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
          $template = 'eventslisting';
          $calendarIds = $this->parameterService['CalendarId'];
          $cookieName = $this->parameterService['DownloadCookieName'];
          $cookieValue = $this->parameterService['DownloadCookieValue'];

          if (empty($cookieName) || empty($cookieValue)) {
            return self::grumble($this->l->t('Download-cookies have not been submitted'));
          }

          if (count($selected) > 0) {
            $exports = [];
            foreach ($selected as $eventUri) {
              $exports[$eventUri] = $calendarIds[$eventUri];
            }
          } else {
            $exports = $calendarIds;
          }

          $fileName = $projectName.'-'.$this->timeStamp().'.ics';

          $response = new DataDownloadResponse(
            $this->eventsService->exportEvents($exports, $projectName),
            $fileName,
            'text/calendar');

          $response->addCookie($cookieName, $cookieValue);

          return $response;

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
        'requestToken' => \OCP\Util::callRegister(),
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
