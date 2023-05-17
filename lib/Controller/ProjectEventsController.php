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

use Throwable;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\ToolTipsService;

use OCA\CAFEVDB\Exceptions;

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
   * Provide a value for an input element (select option, input) which codes
   * all neccessary info to uniquely identify the event.
   *
   * @param array $event
   *
   * @return string
   */
  public static function makeInputValue(array $event):string
  {
    return json_encode([
      'calendarId' => $event['calendarId'],
      'uri' => $event['uri'],
      'recurrenceId' => $event['recurrenceId' ] ?? 0,
      'seriesUid' => $event['seriesUid'] ?? '',
    ]);
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

      $selectedEvents = array_unique($this->parameterService->getParam('eventSelect', []));
      $calendarIds = array_unique($this->parameterService->getParam('calendarId', []));

      $selected = []; // array marking selected events
      foreach ($selectedEvents as $eventIdentifier) {
        $eventIdentifier = json_decode($eventIdentifier, true);
        $flatIdentifier = EventsService::makeFlatIdentifier($eventIdentifier);
        $selected[$flatIdentifier] = $eventIdentifier;
      }

      $eventIdentifier = $this->parameterService->getParam('eventIdentifier');
      if (!empty($eventIdentifier)) {
        $eventIdentifier = json_decode($eventIdentifier, true);
        $flatIdentifier = EventsService::makeFlatIdentifier($eventIdentifier);
        $scope = $this->parameterService->getParam('scope');
        if (!empty($scope[$flatIdentifier])) {
          $scope = $scope[$flatIdentifier];
        }
      }

      $events = null;
      switch ($topic) {
        case 'dialog': // open
          $template = 'project-events/events';
          break;
        case 'redisplay':
          $template = 'project-events/eventslisting';
          break;
        case 'absenceField':
          $template = 'project-events/eventslisting';

          $enable = $this->parameterService->getParam('enableAbsenceField', false);
          $enable = filter_var($enable, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);

          $calendarId = $eventIdentifier['calendarId'];
          $eventUri = $eventIdentifier['uri'];
          $recurrenceId = $eventIdentifier['recurrenceId'];

          $category = EventsService::getRecordAbsenceCategory($this->appL10n());
          if ($enable) {
            $removals = [];
            $additions = [ $category ];
          } else {
            $removals = [ $category ];
            $additions = [];
          }

          switch ($scope) {
            case 'series':
              $recurrenceId = null;
              // fall through
            case 'single':
              try {
                $this->eventsService->changeCategories(
                  $projectId,
                  $calendarId,
                  $eventUri,
                  $recurrenceId,
                  additions: $additions,
                  removals: $removals,
                );
              } catch (Exceptions\CalendarEntryNotFoundException $e) {
                // ignore
              }
              break;
            case 'related':
              $seriesUid = $eventIdentifier['seriesUid'];
              $candidates = [];
              foreach ($this->eventsService->events($projectId) as $event) {
                if ($event['seriesUid'] == $seriesUid) {
                  $candidates[$event['calendarid']][$event['uri']] = true;
                }
              }
              foreach ($candidates as $calendarId => $uris) {
                foreach (array_keys($uris) as $eventUri) {
                  try {
                    $this->eventsService->changeCategories(
                      $projectId,
                      $calendarId,
                      $eventUri,
                      recurrenceId: null,
                      additions: $additions,
                      removals: $removals,
                    );
                  } catch (Exceptions\CalendarEntryNotFoundException $e) {
                    // ignore
                  }
                }
              }
              break;
          }
          break;
        case 'select':
          $template = 'project-events/eventslisting';
          $events = $this->eventsService->events($projectId);
          $selected = []; // array marking selected events
          foreach ($events as $event) {
            $flatIdentifier = EventsService::makeFlatIdentifier($event);
            $selected[$flatIdentifier] = true;
          }
          break;
        case 'deselect':
          $template = 'project-events/eventslisting';
          $selected = []; // array marking selected events
          break;
        case 'delete':
          $template = 'project-events/eventslisting';

          $calendarId = $eventIdentifier['calendarId'];
          $eventUri = $eventIdentifier['uri'];
          $recurrenceId = $eventIdentifier['recurrenceId'];

          switch ($scope) {
            case 'single':
              $this->eventsService->deleteCalendarEntry($calendarId, $eventUri, $recurrenceId);
              $this->eventsService->unregister($projectId, $eventUri, $recurrenceId);
              unset($selected[$flatIdentifier]);
              break;
            case 'series':
              $this->eventsService->deleteCalendarEntry($calendarId, $eventUri, recurrenceId: null);
              $this->eventsService->unregister($projectId, $eventUri, recurrenceId: null);
              $seriesIdentifier = implode(':', [ $calendarId, $eventUri ]);
              $selected = array_filter(
                $selected,
                fn($flatIdentifier) => !str_starts_with($flatIdentifier, $seriesIdentifier),
                ARRAY_FILTER_USE_KEY,
              );
              break;
            case 'related':
              $seriesUid = $eventIdentifier['seriesUid'];
              $candidates = [];
              foreach ($this->eventsService->events($projectId) as $event) {
                if ($event['seriesUid'] == $seriesUid) {
                  $candidates[$event['calendarid']][$event['uri']] = true;
                }
              }
              foreach ($candidates as $calendarId => $uris) {
                foreach (array_keys($uris) as $eventUri) {
                  $this->eventsService->deleteCalendarEntry($calendarId, $eventUri, recurrenceId: null);
                  $this->eventsService->unregister($projectId, $eventUri, recurrenceId: null);
                  $seriesIdentifier = implode(':', [ $calendarId, $eventUri ]);
                  $selected = array_filter(
                    $selected,
                    fn($flatIdentifier) => !str_starts_with($flatIdentifier, $seriesIdentifier),
                    ARRAY_FILTER_USE_KEY,
                  );
                }
              }
              break;
          }
          break;
        case 'detach':
          $template = 'project-events/eventslisting';

          $calendarId = $eventIdentifier['calendarId'];
          $eventUri = $eventIdentifier['uri'];
          $recurrenceId = $eventIdentifier['recurrenceId'];

          switch ($scope) {
            case 'single':
              $this->eventsService->unchain($projectId, $calendarId, $eventUri, $recurrenceId);
              unset($selected[$flatIdentifier]);
              break;
            case 'series':
              $this->eventsService->unchain($projectId, $calendarId, $eventUri, recurrenceId: null);
              $seriesIdentifier = implode(':', [ $calendarId, $eventUri ]);
              $selected = array_filter(
                $selected,
                fn($flatIdentifier) => !str_starts_with($flatIdentifier, $seriesIdentifier),
                ARRAY_FILTER_USE_KEY,
              );
              break;
            case 'related':
              $seriesUid = $eventIdentifier['seriesUid'];
              $candidates = [];
              foreach ($this->eventsService->events($projectId) as $event) {
                if ($event['seriesUid'] == $seriesUid) {
                  $candidates[$event['calendarid']][$event['uri']] = true;
                }
              }
              foreach ($candidates as $calendarId => $uris) {
                foreach (array_keys($uris) as $eventUri) {
                  $this->eventsService->unchain($projectId, $calendarId, $eventUri, recurrenceId: null);
                  $seriesIdentifier = implode(':', [ $calendarId, $eventUri ]);
                  $selected = array_filter(
                    $selected,
                    fn($flatIdentifier) => !str_starts_with($flatIdentifier, $seriesIdentifier),
                    ARRAY_FILTER_USE_KEY,
                  );
                }
              }
              break;
          }
          break;
        case 'download':
          $exports = $selected;
          if (empty($exports)) {
            foreach ($calendarIds as $eventUri => $calendarId) {
              $exports[] = [ 'calendarId' => $calendarId, 'uri' => $eventUri ];
            }
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
        'appName' => $this->appName,
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
        'urlGenerator' => $this->urlGenerator(),
        'requesttoken' => \OCP\Util::callRegister(),
        'wikinamespace' => $this->getAppValue('wikinamespace'),
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

    } catch (Throwable $t) {
      return self::grumble($this->exceptionChainData($t));
    }
  }
}
