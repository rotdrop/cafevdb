<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\ToolTipsService;

/** AJAX end-points to manage events linked to projects */
class ProjectEventsController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var \OCA\CAFEVDB\Service\ParameterService */
  private $parameterService;

  /** @var \OCA\CAFEVDB\Service\EventsService */
  private $eventsService;

  /** @var \OCA\CAFEVDB\Service\CalDavService */
  private $calDavService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    RequestParameterService $parameterService,
    ConfigService $configService,
    EventsService $eventsService,
    CalDavService $calDavService,
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->eventsService = $eventsService;
    $this->calDavService = $calDavService;
    $this->l = $this->l10N();
  }
  // phpcs:enable

  /**
   * @param array $eventIdentifier
   *
   * @return string
   */
  private static function makeFlatIdentifier(array $eventIdentifier):string
  {
    return implode(':', [
      $eventIdentifier['calendarId'],
      $eventIdentifier['uri'],
      $eventIdentifier['recurrenceId'],
    ]);
  }

  /**
   * @param string $flatIdentifier
   *
   * @return array
   */
  private static function explodeFlagIdentifier(string $flatIdentifier):array
  {
    list($calendarId, $uri, $recurrenceId, ) = explode(':', $flatIdentifier);
    return compact('calendarId', 'uri','recurrenceId');
  }

  /**
   * @param string $topic
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   */
  public function serviceSwitch(string $topic):Http\Response
  {
    try {
      $projectId = $this->parameterService['projectId'];
      $projectName = $this->parameterService['projectName'];

      if (empty($projectId) || empty($projectName)) {
        return self::grumble(
          $this->l->t(
            'Project-id AND -name have to be specified (%s / %s)',
            [ empty($projectId) ? '?' : $projectId,
              empty($projectName) ? '?' : $projectName ]));
      }

      $selectedEvents = $this->parameterService->getParam('eventSelect', []);
      $calendarIds = $this->parameterService['calendarId'];

      $selected = []; // array marking selected events
      foreach ($selectedEvents as $eventIdentifier) {
        $eventIdentifier = json_decode($eventIdentifier, true);
        $flatIdentifier = self::makeFlatIdentifier($eventIdentifier);
        $selected[$flatIdentifier] = true;
      }

      $eventIdentifier = $this->parameterService->getParam('eventIdentifier');
      if (!empty($eventIdentifier)) {
        $eventIdentifier = json_decode($eventIdentifier, true);
        $flatIdentifier = self::makeFlatIdentifier($eventIdentifier);
      }

      $events = null;
      switch ($topic) {
        case 'dialog': // open
          $template = 'events';
          break;
        case 'redisplay':
          $template = 'eventslisting';
          break;
        case 'select':
          $template = 'eventslisting';
          $events = $this->eventsService->events($projectId);
          $selected = []; // array marking selected events
          foreach ($events as $event) {
            $flatIdentifier = self::makeFlatIdentifier($event);
            $selected[$flatIdentifier] = true;
          }
          break;
        case 'deselect':
          $template = 'eventslisting';
          $selected = []; // array marking selected events
          break;
        case 'delete':
          $template = 'eventslisting';

          $calendarId = $eventIdentifier['calendarId'];
          $eventUri = $eventIdentifier['uri'];
          $recurrenceId = $eventIdentifier['recurrenceId'];

          $this->eventsService->deleteCalendarEntry($calendarId, $eventUri, $recurrenceId);
          $this->eventsService->unregister($projectId, $eventUri, $recurrenceId);

          $selected = array_filter($selected, fn($value, $key) => !str_starts_with($key, $flatIdentifier), ARRAY_FILTER_USE_BOTH);
          break;
        case 'detach':
          $template = 'eventslisting';

          $calendarId = $eventIdentifier['calendarId'];
          $eventUri = $eventIdentifier['uri'];
          $recurrenceId = $eventIdentifier['recurrenceId'];

          $this->eventsService->unchain($projectId, $calendarId, $eventUri, $recurrenceId);

          $selected = array_filter($selected, fn($value, $key) => !str_starts_with($key, $flatIdentifier), ARRAY_FILTER_USE_BOTH);
          break;
        case 'download':
          $exports = [];
          foreach (array_keys($selected) as $flatIdentifier) {
            $eventIdentifier = self::explodeFlagIdentifier($flatIdentifier);
            $exports[$eventIdentifier['uri']] = $eventIdentifier['calendarId'];
          }
          if (empty($exports)) {
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

      if ($events === null) {
        $events = $this->eventsService->events($projectId);
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
