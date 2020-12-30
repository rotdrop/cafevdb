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

  /** @var IL10N */
  private $l;

  /** @var ToolTipsService */
  private $toolTipsService;

  /** @var GeoCodingService */
  private $geoCodingService;

  /** @var EventsService */
  private $eventsService;

  /** @var CalDavService */
  private $calDavService;

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
    , EventsService $eventsService
    , CalDavService $calDavService
    , instrumentationService $instrumentationService
    , PageNavigation $pageNavigation
  ) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->toolTipsService = $toolTipsService;
    $this->pageNavigation = $pageNavigation;
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

    // maybe restrict this to the group admins
    $templateParameters = [
      'appName' => $this->appName(),
      'expertMode' => $this->getUserValue('expertmode', 'off'),
      'showToolTips' => $this->getUserValue('tooltips', 'on'),
      'toolTips' => $this->toolTipsService,
      'pageNavigation' => $this->pageNavigation,
    ];
    $links = [
      'phpmyadmin'
      , 'phpmyadmincloud'
      , 'sourcecode'
      , 'sourcedocs'
      , 'clouddev'
      , 'cspfailure'
    ];
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
      case 'attachwebpages':
      case 'sanitizephones':
        break;
      case 'setupdb':
      case 'makeviews':
        try {
          $this->instrumentationService->createJoinTableViews();
        } catch (\Throwable $t) {
          $this->logException($t);
          return self::grumble($this->exceptionChainData($t));
        }
        return self::response($this->l->t('Database maintenance succeeded'));
        break;
      case 'geodata':
        $this->geoCodingService->updateCountries();
        $this->geoCodingService->updatePostalCodes(null, 1, [
          [ 'country' => 'de', 'postalCode' => '71229' ]
        ]);
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
