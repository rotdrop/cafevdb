<?php
/* Orchestra member, musician and project management application.
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
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\Projects as Renderer;

class ProjectParticipantsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit */
  protected $pme;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var EntityManager */
  protected $entityManager;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->pme = $phpMyEdit;
    $this->l = $this->l10N();
    $this->setDatabaseRepository(Entities\ProjectParticipant::class);
  }

  /**
   * @NoAdminRequired
   *
   * @TODO implement instruments check
   */
  public function serviceSwitch($topic, $recordId = [], $instrumentValues = [])
  {
    $this->logDebug($topic.' / '.print_r($recordId, true).' / '.print_r($instrumentValues, true));
    if (empty($instrumentValues)) {
      $instrumentValues = [];
    }

    switch ($topic) {
    case 'change-musician-instruments':
    case 'change-project-instruments':
      if (empty($recordId['projectId']) || empty($recordId['musicianId'])) {
        return self::grumble($this->l->t("Project- or musician-id is missing (%s/%s)",
                                         [ $recordId['projectId'], $recordId['musicianId'], ]));
      }

      $projectParticipant = $this->find([ 'project' => $recordId['projectId'], 'musician' => $recordId['musicianId'] ]);
      if (empty($projectParticipant)) {
        return self::grumble($this->l->t("Unable to fetch project-participant with given key %s", print_r($recordId, true)));
      }

      $musicianInstruments = [];
      foreach ($projectParticipant['musician']['instruments'] as $musicianInstrument) {
        $musicianInstruments[$musicianInstrument['instrument']['id']] = $musicianInstrument;
      }

      $projectInstruments = [];
      foreach ($projectParticipant['projectInstruments'] as $projectInstrument) {
        $projectInstruments[$projectInstrument['instrument']['id']] = $projectInstrument;
      }

      $allInstruments = [];
      foreach ($this->getDatabaseRepository(Entities\Instrument::class)->findAll() as $instrument) {
        $allInstruments[$instrument['id']] = $instrument;
      }

      $this->logDebug('PRJ INST '.print_r(array_keys($projectInstruments), true));
      $this->logDebug('MUS INST '.print_r(array_keys($musicianInstruments), true));
      $this->logDebug('AJX INST '.print_r($instrumentValues, true));

      switch ($topic) {
      case 'change-musician-instruments':

        $message   = [];

        // This should be cheap as most musicians only play very few instruments
        foreach (array_diff(array_keys($musicianInstruments), $instrumentValues) as $removedId) {

          if (isset($projectInstruments[$removedId])) {
            return self::grumble($this->l->t('Denying the attempt to remove the instrument %s because it is used in the current project.', $projectInstruments[$removedId]['instrument']['name']));
          }

          /** @TODO implement soft-deletion */
          if ($musicianInstruments[$removedId]->usage() > 0) {
            return self::grumble(
              $this->l->t(
                'Denying the attempt to remove the instrument %s because it is still used in %s other contexts.',
                [ $musicianInstruments['instrument']['name'], $musicianInstruments[$removedId]->usage() ]));
          }

          $message[]  = $this->l->t(
            'Removing instrument %s from the list of instruments played by %s',
            [ $musicianInstruments[$removedId]['instrument']['name'],
              $projectParticipant['musician']['firstName'] ]);

        }

        foreach (array_diff($instrumentValues, array_keys($musicianInstruments)) as $addedId) {
          if (!isset($allInstruments[$addedId])) {
            return self::grumble($this->l->t('Denying the attempt to add an unknown instrument (id = %s)',
                                             $addedId));
          }
          $message[] = $this->l->t(
            'Adding instrument %s to the list of instruments played by %s',
            [ $allInstruments[$addedId]['name'],
              $projectParticipant['musician']['firstName'] ]);
        }

        // all ok
        return self::response(implode('; ', $message));

      case 'change-project-instruments':

        $message   = [];

        // removing instruments should be just fine
        foreach (array_diff(array_keys($instrumentValues, $projectInstruments)) as $addedId) {

          if (!isset($allInstruments[$addedId])) {
            return self::grumble(
              $this->l->t('Denying the attempt to add an unknown instrument (id = %s)',
                          $addedId));
          }

          if (!isset($musicianInstruments[$addedId])) {
            // should not happen unless the UI is broken
            return self::grumble(
              $this->l->t(
                'Denying the attempt to add the instrument %s because %s cannot play it.',
                [ $allInstruments['name'],
                  $projectParticipant['musician']['firstName'] ]));
          }

          $message[] = $this->l->t(
            'Adding instrument %s to the list of project-instruments of %s',
            [ $allInstruments[$addedId]['name'],
              $projectParticipant['musician']['firstName'] ]);
        }

        // all ok
        return self::response(implode('; ', $message));

      }
      return self::response($this->l->t('Validation not yet implemented'));
      break;
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
