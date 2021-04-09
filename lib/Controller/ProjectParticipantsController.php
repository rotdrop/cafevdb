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
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\Projects as Renderer;

use OCA\CAFEVDB\Common\Util;

class ProjectParticipantsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit */
  protected $pme;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var ProjectService */
  private $projectService;

  /** @var EntityManager */
  protected $entityManager;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
    , ProjectService $projectService
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->pme = $phpMyEdit;
    $this->projectService = $projectService;
    $this->l = $this->l10N();
    $this->setDatabaseRepository(Entities\ProjectParticipant::class);
  }

  /**
   * @NoAdminRequired
   *
   * @param string $topic
   */
  public function addMusicians($projectId, $projectName, $musicianId = null)
  {
    $this->logInfo($projectId.' '.$projectName.' '.$muicianId);

    // Multi-mode:
    // projectId: ID
    // projectName: NAME
    // PME_sys_mrecs[] = [ id1, ... ]
    //
    // Single mode:
    // projectId: ID
    // projectName: NAME
    // musicianId: 1
    $musicianIds = [];
    if (!empty($musicianId) && $musicianId > 0) {
      $musicianIds[] = $musicianId;
    } else {
      $musicianIds = $this->parameterService->getParam($this->pme->cgiSysName('mrecs'), []);
    }

    $this->logInfo('Requested participants '.print_r($musicianIds, true));

    $numRecords = count($musicianIds);
    if ($numRecords == 0) {
      return self::grumble($this->l->t('Missing Musician Ids'));
    }

    if (empty($projectId) || $projectId <= 0) {
      return self::grumble($this->l->t('Missing Project Id'));
    }

    $result = $this->projectService->addMusicians($musicianIds, $projectId);

    $failedMusicians = $result['failed'];
    $addedMusicians  = $result['added'];

    $this->logInfo("RESULT ".print_r($result, true).' '. count($failedMusicians).' '.$numRecords);

    if ($numRecords == count($failedMusicians)) {

      $message = $this->l->t('No musician could be added to the project, #failures: %d.',
                             count($failedMusicians));

      foreach ($failedMusicians as $id => $failures) {
        foreach ($failures as $failure) {
          $message .= ' '.$failure['notice'];
        }
      }

      return self::grumble($message);

    } else {

      $notice = '';
      $musicians = [];
      foreach ($addedMusicians as $id => $notices) {
        $musicians[] = $id;
        foreach ($notices as $notice) {
          $notice .= $notice['notice'];
        }
      }

      return self::dataResponse(
        [
          'musicians' => $musicians,
          'message' => ($notice == ''
                        ? '' // don't annoy the user with success messages.
                        : $this->l->t("Operation succeeded with the following notifications:")),
          'notice' => $notice,
        ]);
    }

    return self::grumble($this->l->t('Unknown Request'));
  }

  /**
   * @NoAdminRequired
   *
   * @param string $topic
   * - change-musician-instruments
   * - change-project-instruments
   */
  public function changeInstruments($context, $recordId = [], $instrumentValues = [])
  {
    $this->logDebug($context.' / '.print_r($recordId, true).' / '.print_r($instrumentValues, true));
    if (empty($instrumentValues)) {
      $instrumentValues = [];
    }

    switch ($context) {
    case 'musician':
    case 'project':
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

      switch ($context) {
      case 'musician':

        $message   = [];

        // This should be cheap as most musicians only play very few instruments
        foreach (array_diff(array_keys($musicianInstruments), $instrumentValues) as $removedId) {

          if (isset($projectInstruments[$removedId])) {
            return self::grumble($this->l->t('Denying the attempt to remove the instrument %s because it is used in the current project.', $projectInstruments[$removedId]['instrument']['name']));
          }

          /** @todo implement soft-deletion */
          if ($musicianInstruments[$removedId]->usage() > 0) {
            // return self::grumble(
            //   $this->l->t(
            //     'Denying the attempt to remove the instrument %s because it is still used in %s other contexts.',
            //     [ $musicianInstruments['instrument']['name'], $musicianInstruments[$removedId]->usage() ]));

            // soft-delete works, but we still want to dis-allow
            // deleting instrument used in _this_ project.
            $message[] = $this->l->t(
              'Just marking the instrument %s as disabled because it is still used in %s other contexts.',
                [ $musicianInstruments['instrument']['name'], $musicianInstruments[$removedId]->usage() ]);
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

      case 'project':

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
    return self::grumble($this->l->t('Unknown Request %s', $context));
  }

  /**
   * @NoAdminRequired
   *
   * @param string $topic
   *
   * @todo There should be an upload support class handling this stuff
   */
  public function upload($source)
  {
    $upload_max_filesize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
    $post_max_size = \OCP\Util::computerFileSize(ini_get('post_max_size'));
    $maxUploadFileSize = min($upload_max_filesize, $post_max_size);
    $maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);

    switch ($source) {
    case 'upload':
      $fileKey = 'files';
      if (empty($_FILES[$fileKey])) {
        // may be caused by PHP restrictions which are not caught by
        // error handlers.
        $contentLength = $this->request->server['CONTENT_LENGTH'];
        $limit = \OCP\Util::uploadLimit();
        if ($contentLength > $limit) {
          return self::grumble(
            $this->l->t('Upload size %s exceeds limit %s, contact your server administrator.', [
              \OCP\Util::humanFileSize($contentLength),
              \OCP\Util::humanFileSize($limit),
            ]));
        }
        $error = error_get_last();
        if (!empty($error)) {
          return self::grumble(
            $this->l->t('No file was uploaded, error message was "%s".', $error['message']));
        }
        return self::grumble($this->l->t('No file was uploaded. Unknown error'));
      }

      $files = Util::transposeArray($_FILES[$fileKey]);

      $totalSize = 0;
      foreach ($files as &$file) {

        $totalSize += $file['size'];

        if ($maxUploadFileSize >= 0 and $totalSize > $maxUploadFileSize) {
          return self::grumble([
            'message' => $this->l->t('Not enough storage available'),
            'upload_max_file_size' => $maxUploadFileSize,
            'max_human_file_size' => $maxHumanFileSize,
          ]);
        }

        $file['upload_max_file_size'] = $maxUploadFileSize;
        $file['max_human_file_size']  = $maxHumanFileSize;
        $file['original_name'] = $file['name']; // clone

        $file['str_error'] = Util::fileUploadError($file['error'], $this->l);
        if ($file['error'] != UPLOAD_ERR_OK) {
          continue;
        }

        // // Move the temporary files to locations where we can find them later.
        // if ($composer->saveAttachment($file) === false) {
        //   $file['error'] = 99;
        //   $file['str_error'] = $this->l->t('Couldn\'t save temporary file for: %s', $file['name']);
        //   continue;
        // }
      }
      return self::dataResponse($files);
    default:
      break;
    }
    return self::grumble($this->l->t('Unknown Request %s', $source));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
