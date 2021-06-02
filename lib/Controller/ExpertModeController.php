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
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\InstrumentationService;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

class ExpertModeController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  const ERROR_TEMPLATE = "errorpage";
  const TEMPLATE = "expertmode";

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var GeoCodingService */
  private $geoCodingService;

  /** @var EventsService */
  private $eventsService;

  /** @var CalDavService */
  private $calDavService;

  /** @var ProjectService */
  private $projectService;

  /** @var InstrumentationService */
  private $instrumentationService;

  /** @var OCA\CAFEVDB\PageRenderer\Util\Navigation */
  private $pageNavigation;

  public function __construct(
    $appName
    , IRequest $request
    , ConfigService $configService
    , ToolTipsService $toolTipsService
    , GeoCodingService $geoCodingService
    , ProjectService $projectService
    , EventsService $eventsService
    , CalDavService $calDavService
    , instrumentationService $instrumentationService
    , PageNavigation $pageNavigation
  ) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->toolTipsService = $toolTipsService;
    $this->pageNavigation = $pageNavigation;
    $this->projectService = $projectService;
    $this->geoCodingService = $geoCodingService;
    $this->eventsService = $eventsService;
    $this->calDavService = $calDavService;
    $this->instrumentationService = $instrumentationService;
    $this->l = $this->l10N();
  }

  /**
   * Return settings form
   *
   * @NoAdminRequired
   */
  public function action($operation, $data) {
    switch ($operation) {
      case 'syncevents':
        $result = [];
        //$calendarIds = $this->eventsService->defaultCalendars();
        $calendars = $this->calDavService->getCalendars(true);
        foreach ($calendars as $calendar) {
          if (!$this->calDavService->isGroupSharedCalendar($calendar->getKey(), $this->groupId()))  {
            continue;
          }
          $events = $calendar->search('', [], [ 'types' => [ 'VEVENT' ] ]);
          foreach ($events as $event) {
            $eventData = $this->calDavService->getCalendarObject($calendar->getKey(), $event['uri']);
            if (empty($eventData)) {
              $this->logError('Unable to fetch event '.$event['uri']);
            }
            $status = $this->eventsService->syncCalendarObject($eventData);
            if (!empty($status['registered']) || !empty($status['unregistered'])) {
              $result[$event['uri']] = $status;
            }
          }
        }
        return self::valueResponse($result, print_r($result, true));
      case 'wikiprojecttoc':
        $this->projectService->generateWikiOverview();
        break;
      case 'attachwebpages':
        $projects = $this->projectService->fetchAll();
        foreach ($projects as $project) {
          $this->projectService->attachMatchingWebPages($project);
        }
        break;
      case 'sanitizephones':
        break;
      case 'setupdb':
        break;
      case 'geodata':
        $this->geoCodingService->updateCountries();
        // $this->geoCodingService->updatePostalCodes(null, 1, [
        //   [ 'country' => 'de', 'postalCode' => '71229' ]
        // ]);
        $this->geoCodingService->updatePostalCodes(null, 100);
        return self::response($this->l->t('Triggered GeoCoding update.'));
        break;
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
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
