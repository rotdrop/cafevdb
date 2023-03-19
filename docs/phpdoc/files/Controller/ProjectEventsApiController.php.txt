<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\Http\DataResponse;

use OCP\Authentication\LoginCredentials\IStore;

use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/** API for project events. */
class ProjectEventsApiController extends OCSController
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const INDEX_BY_PROJECT = 'byProject';
  const INDEX_BY_WEB_PAGE = 'byWebPage';

  /** @var EventsService */
  private $eventsService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    ConfigService $configService,
    EventsService $eventsService,
    EntityManager $entityManager,
  ) {
    parent::__construct($appName, $request);
    $this->configService = $configService;
    $this->eventsService = $eventsService;
    $this->entityManager = $entityManager;
    $this->l = $this->l10n();
    // if (false) {
    //   try {
    //     $credentialsStore = $this->di(IStore::class);
    //     $credentials = $credentialsStore->getLoginCredentials();
    //     //$this->logInfo($credentials->getLoginName() . ' ' . $credentials->getPassword());
    //   } catch (\Throwable $t) {
    //     $this->logException($t);
    //   }
    // }
  }
  // phpcs:enable

  /**
   * @param string $indexObject
   *
   * @param int $objectId
   *
   * @param string $calendar
   *
   * @param string $timezone
   *
   * @param string $locale
   *
   * @return DataResponse
   *
   * @CORS
   * @NoCSRFRequired
   * @NoAdminRequired
   */
  public function serviceSwitch(
    string $indexObject,
    int $objectId,
    string $calendar,
    string $timezone,
    string $locale,
  ):DataResponse {
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
