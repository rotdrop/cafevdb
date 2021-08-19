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

use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\Http\DataResponse;

use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

class ProjectEventsApiController extends OCSController
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const INDEX_BY_PROJECT = 'byProject';
  const INDEX_BY_WEB_PAGE = 'byWebPage';

  /** @var EventsService */
  private $eventsService;

  public function __construct(
    $appName
    , IRequest $request
    , ConfigService $configService
    , EventsService $eventsService
    , EntityManager $entityManager
  ) {
    parent::__construct($appName, $request);
    $this->configService = $configService;
    $this->eventsService = $eventsService;
    $this->entityManager = $entityManager;
    $this->l = $this->l10n();
  }

  /**
   * @CORS
   * @NoCSRFRequired
   * @NoAdminRequired
   */
  public function serviceSwitch($indexObject, $objectId, $calendar, $timezone, $locale)
  {
    // OC uses symphony which rawurldecodes the request URL. This
    // implies that in order to pass a slash / we caller must
    // urlencode that thingy twice, and Symphony consequently will
    // only deliver encoded data in this case.

    $timezone = $timezone ?? $this->getTimezone();
    $locale = $locale ?? $this->getLocale();
    $timezone = rawurldecode($timezone);
    $locale = rawurldecode($locale);

    switch ($indexObject) {
    case self::INDEX_BY_PROJECT:
      $projectId = $objectId;
      $calendar == 'all' && $calendar = null;
      $eventData = $this->eventsService->projectEventData($projectId, $calendar, $timezone, $locale);
      return new DataResponse($eventData);
    case self::INDEX_BY_WEB_PAGE:
      $articleId = $objectId;
      $timeZone = $timeZone ?? $this->getTimezone();
      $locale = $locale ?? $this->getLocale();

      switch ($calendar) {
      case 'all':
        $calendar = null;
        break;
      case 'concerts':
      case 'rehearsals':
      case 'other':
        $calendar = $this->getConfigValue($calendar.'calendar'.'id');
        break;
      default:
        throw new OCSException\OCSBadRequestException($this->l->t('Invalid calendar type: "%1$s"', $calendar));
      }

      $articles = $this->getDatabaseRepository(Entities\ProjectWebPage::class)
                       ->findBy(['articleId' => $articleId ], [ 'project' => 'ASC', 'articleId' => 'ASC' ]);

      $data = [];
      /** @var Entities\ProjectWebPage $article */
      foreach ($articles as $article) {
        $project = $article->getProject();
        $data[$project->getName()] = $this->eventsService->projectEventData($project->getId(), $calendar, $timezone, $locale);
      }
      return new DataResponse($data);
    default:
      throw new OCS\OCSNotFoundException;
    }
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
