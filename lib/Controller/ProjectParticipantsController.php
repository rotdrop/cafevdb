<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;

use OCA\CAFEVDB\Common\Util;

class ProjectParticipantsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Controller\FileUploadRowTrait;

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
    $this->logInfo($projectId.' '.$projectName.' '.$musicianId);

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
    if (!empty($musicianId)) {
      $musicianIds[] = $musicianId;
    } else {
      $musicianIds = array_map(
        function($value) { $id = json_decode($value, true); return $id['id']??$id; },
        $this->parameterService->getParam($this->pme->cgiSysName('mrecs'), [])
      );

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

      $messages[] = $this->l->t('No musician could be added to the project, #failures: %d.',
                                count($failedMusicians));

      foreach ($failedMusicians as $id => $failures) {
        foreach ($failures as $failure) {
          $messages[] = ' '.$failure['notice'];
        }
      }

      return self::grumble([ 'message' => $messages ]);

    } else {

      $messages = [];
      $aggregateNotice = '';
      $musicians = [];
      foreach ($addedMusicians as $id => $notices) {
        $musicians[] = $id;
        foreach ($notices as $notice) {
          $messages[] = $notice['notice'];
        }
      }

      return self::dataResponse(
        [
          'musicians' => $musicians,
          'message' => $messages,
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
      // sort by musician's instrument ranking
      uasort($musicianInstruments, function($a, $b) {
        /** @var Entities\MusicianInstrument $a */
        /** @var Entities\MusicianInstrument $b */
        $ra = $a->getRanking();
        $rb = $b->getRanking();
        return (($ra === $rb) ? 0 : (($ra <= $rb) ? -1 : 1));
      });

      $projectInstruments = [];
      foreach ($projectParticipant['projectInstruments'] as $projectInstrument) {
        $projectInstruments[$projectInstrument['instrument']['id']] = $projectInstrument;
      }

      $allInstruments = [];
      foreach ($this->getDatabaseRepository(Entities\Instrument::class)->findAll() as $instrument) {
        $allInstruments[$instrument['id']] = $instrument;
      }

      $this->logInfo('PRJ INST '.print_r(array_keys($projectInstruments), true));
      $this->logInfo('MUS INST '.print_r(array_keys($musicianInstruments), true));
      $this->logInfo('AJX INST '.print_r($instrumentValues, true));

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
            // soft-delete works, but we still want to dis-allow
            // deleting instrument used in _this_ project.
            $message[] = $this->l->t(
              'Just marking the instrument "%1$s" as disabled because it is still used in %2$d other contexts.',
                [ $musicianInstruments['instrument']['name'], $musicianInstruments[$removedId]->usage() ]);
          }

          $message[]  = $this->l->t(
            'Removing instrument "%1$s" from the list of instruments played by %2$s.',
            [ $musicianInstruments[$removedId]['instrument']['name'],
              $projectParticipant['musician']['firstName'] ]);

        }

        foreach (array_diff($instrumentValues, array_keys($musicianInstruments)) as $addedId) {
          if (!isset($allInstruments[$addedId])) {
            return self::grumble($this->l->t('Denying the attempt to add an unknown instrument (id = %s)',
                                             $addedId));
          }
          $message[] = $this->l->t(
            'Adding instrument "%s" to the list of instruments played by "%s"',
            [ $allInstruments[$addedId]['name'],
              $projectParticipant['musician']['firstName'] ]);
        }

        // see if the primary instrument has been changed
        if (count($instrumentValues) > 1
            && $instrumentValues[0] != (array_keys($musicianInstruments)[0]??0)) {
          $message[] = $this->l->t(
            'Setting "%1$s" as the primary instrument of %2$s.',
            [ $allInstruments[$instrumentValues[0]]['name'], $projectParticipant['musician']['firstName'], ]);
        }

        // all ok
        return self::response($message);

      case 'project':

        $message   = [];

        // removing instruments should be just fine
        foreach (array_diff(array_keys($instrumentValues, $projectInstruments)) as $addedId) {

          if (!isset($allInstruments[$addedId])) {
            return self::grumble(
              $this->l->t('Denying the attempt to add an unknown instrument (id = %s).',
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
            'Adding instrument "%1$s" to the list of project-instruments of %s.',
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
   * @param string $operation
   *
   * @todo There should be an upload support class handling this stuff
   */
  public function files($operation, $musicianId, $projectId, $fieldId, $optionKey, $subDir, $fileName, $data, $files = null)
  {
    $upload_max_filesize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
    $post_max_size = \OCP\Util::computerFileSize(ini_get('post_max_size'));
    $maxUploadFileSize = min($upload_max_filesize, $post_max_size);
    $maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);

    /** @var Entities\ProjectParticipant $participant */
    $participant = $this->getDatabaseRepository(Entities\ProjectParticipant::class)
                        ->find(['project' => $projectId, 'musician' => $musicianId]);
    $project = $participant->getProject();
    $musician = $participant->getMusician();

    /** @var UserStorage $userStorage */
    $userStorage = $this->di(UserStorage::class);

    switch ($operation) {
    case 'delete':
      /** @var Entities\ProjectParticipantField $field */
      $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);

      /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
      $fieldDatum = $participant->getParticipantFieldsDatum($optionKey);
      if (empty($fieldDatum)) {
        return self::grumble($this->l->t('Unable to find data for option key "%s".', $optionKey));
      }

      $subDirPrefix = empty($subDir) ? '' : UserStorage::PATH_SEP . $subDir;

      $dataType = $field->getDataType();
      switch ($dataType) {
      case FieldDataType::CLOUD_FILE:
        $prefixPath = $this->projectService->ensureParticipantFolder($project, $musician, true);
        $filePath = $prefixPath . $subDirPrefix . UserStorage::PATH_SEP . $fieldDatum->getOptionValue();
        break;

      case FieldDataType::DB_FILE:
        /** @var Entities\EncryptedFile $dbFile */
        $dbFile = $this->getDatabaseRepository(Entities\EncryptedFile::class)
                       ->find($fieldDatum->getOptionValue());
        if (empty($dbFile)) {
          return self::grumble($this->l->t('Unable to find associated file with id "%s" in data-base.',
                                           $fieldDatum->getOptionValue()));
        }
        break;

      case FieldDataType::CLOUD_FOLDER:
        $prefixPath = $this->projectService->ensureParticipantFolder($project, $musician, true);
        $filePath = $prefixPath . $subDirPrefix . UserStorage::PATH_SEP . $fileName;
        break;

      default:
        return self::grumble($this->l->t('Unsupported field type "%s".', $dataType));
      }

      $fileRemoved = false;
      $doDelete = true;
      $this->entityManager->beginTransaction();
      try {
        switch ($dataType) {
        case FieldDataType::CLOUD_FOLDER:
          $userStorage->delete($filePath);
          $optionValue = json_decode($fieldDatum->getOptionValue(), true);
          if (!is_array($optionValue)) {
            $optionValue = [];
          }
          Util::unsetValue($optionValue, $fileName);
          if (!empty($optionValue)) {
            $doDelete = false;
          }
          $fieldDatum->setOptionValue(json_encode(array_values($optionValue)));
          break;
        case FieldDataType::CLOUD_FILE:
          $userStorage->delete($filePath);
          break;
        case FieldDataType::DB_FILE:
          $filePath = $dbFile->getFileName();
          $this->remove($dbFile);
          break;
        }
        if ($doDelete) {
          $this->remove($fieldDatum);
          $this->flush();
          $this->remove($fieldDatum);
        }
        $this->flush();
        $this->entityManager->commit();
      } catch (\Throwable $t) {
        $this->entityManager->rollback();
        throw new \RuntimeException($this->l->t('Unable to delete file "%S".', $filePath), $t->getCode(), $t);
      }
      return self::response($this->l->t('Successfully removed file "%s".', $filePath));
      break;
    case 'upload':

      $uploadData = json_decode($data, true);
      $fieldId = $uploadData['fieldId'];
      $optionKey = $uploadData['optionKey'];
      $uploadPolicy = $uploadData['uploadPolicy'];
      $subDir = $uploadData['subDir']??null;
      $fileName = $uploadData['fileName']??null;

      $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);
      $dataType = $field->getDataType();

      $folderPath = $filePath = '';

      switch ($dataType) {
      case FieldDataType::CLOUD_FOLDER:
      case FieldDataType::CLOUD_FILE:
        $pathChain = [ $this->projectService->ensureParticipantFolder($project, $musician, true), ];
        if ($subDir) {
          $pathChain[] = $subDir;
          $subDir .= UserStorage::PATH_SEP;
        }
        $userStorage->ensureFolderChain($pathChain);
        $folderPath = implode(UserStorage::PATH_SEP, $pathChain);
        if ($dataType === FieldDataType::CLOUD_FILE) {
          $pathChain[] = $this->projectService->participantFilename($uploadData['fileBase'], $project, $musician);
        } else if (!empty($fileName)) {
          $pathChain[] = pathinfo($fileName, PATHINFO_FILENAME);
        }
        $filePath = implode(UserStorage::PATH_SEP, $pathChain);
        break;
      case FieldDataType::DB_FILE:
        if (!empty($subDir)) {
          return self::grumble($this->l->t('Sub-directory "%s" requested, but not supported by db-storage.', $subDir));
        }
        if ($uploadPolicy != 'replace') {
          return self::grumble($this->l->t('Upload-policy "%s" requested, but not supported by storage type "%s".',
                                           [ $uploadPolicy, $dataType ]));
        }
        if (!empty($uploadData['fileBase'])) {
          $filePath = $this->projectService->participantFilename($uploadData['fileBase'], $project, $musician);
        } else if (!empty($fileName)) {
          $filePath = pathinfo($fileName, PATHINFO_FILENAME);
        }
        break;
      default:
        return self::grumble($this->l->t('Unsupported field type "%s".', $dataType));
      }

      /*
       *
       ************************************************************************
       *
       * now the upload stuff which really should be split-out into a
       * support-class
       *
       */

      $files = $this->prepareUploadInfo($files, $optionKey, multiple: $dataType == FieldDataType::CLOUD_FOLDER);

      if ($files instanceof Http\Response) {
        // error generated
        return $files;
      }

      $uploads = [];
      foreach ($files as $index => $file) {

        $messages = []; // messages for non-fatal errors

        if ($file['error'] != UPLOAD_ERR_OK) {
          $this->logInfo('Upload error ' . print_r($file, true));
          continue;
        }

        if ($dataType == FieldDataType::CLOUD_FOLDER && empty($fileName)) {
          // use original name as storage name in the cloud
          $filePath = $folderPath . UserStorage::PATH_SEP . pathinfo($file['name'], PATHINFO_FILENAME);
        } else if ($dataType == FieldDataType::DB_FILE && empty($filePath)) {
          $filePath = pathinfo($file['name'], PATHINFO_FILENAME);
        }

        /*
         * upload successful now try to move the file to its proper
         * location and store it in the data-base.
         *
         ************************************************************************
         *
         * move the file in place.
         *
         */

        $fileCopied = false;
        $oldPath = null;

        $this->entityManager->beginTransaction();
        try {
          $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
          $filePath = $filePath . '.' .$extension;

          $pathInfo = pathinfo($filePath);

          $conflict = null;

          /** @var Entities\ProjectParticipantFieldDatum $fieldData */
          $fieldData = $participant->getParticipantFieldsDatum($optionKey);
          if (empty($fieldData)) {
            $fieldData = (new Entities\ProjectParticipantFieldDatum)
                       ->setField($field)
                       ->setProject($project)
                       ->setMusician($musician)
                       ->setOptionKey($optionKey);
            $participant->getParticipantFieldsData()->add($fieldData);
          } else if (!empty($fieldData->getOptionValue())) {
            switch ($uploadPolicy) {
            case 'rename':
              switch ($dataType) {
              case FieldDataType::DB_FILE:
              case FieldDataType::CLOUD_FOLDER:
                throw new \InvalidArgumentException($this->l->t('Invalid upload-policy "%s" for data type "%s".', [ $uploadPolicy, $dataType ]));
                break;
              case FieldDataType::CLOUD_FILE:
                // Ok, we have to move the old file.
                $oldName = $fieldData->getOptionValue();
                $timeStamp = $this->timeStamp();
                $oldExtension = pathInfo($oldName, PATHINFO_EXTENSION);
                $backupName = $pathInfo['filename'].'-'.$timeStamp;
                if (!empty($oldExtension)) {
                  $backupName .= '.'.$oldExtension;
                }
                $backupPath = $pathInfo['dirname'].UserStorage::PATH_SEP.$backupName;
                $oldPath = $pathChain[0] . UserStorage::PATH_SEP;
                if (!empty($subDir)) {
                  $oldPath .= $pathChain[1] . UserStorage::PATH_SEP;
                }
                $oldPath .= $oldName;
                try {
                  $userStorage->copy($oldPath, $backupPath);
                } catch (\Throwable $t) {
                  $messages[] = $this->l->t('Unable to create a backup-copy of "%1$s" to "%2$s: %3$s".', [ $oldPath, $backupPath, $t->getMessage() ]);
                  $this->logException($t);
                }
                $conflict = 'renamed';
                break;
              }
              break;
            case 'replace':
              switch ($dataType) {
              case FieldDataType::CLOUD_FILE:
                // we still need to populate $oldPath in order to trigger the
                // restore functionality of the cloud.
                $oldPath = $pathChain[0] . UserStorage::PATH_SEP;
                if (!empty($subDir)) {
                  $oldPath .= $pathChain[1] . UserStorage::PATH_SEP;
                }
                $oldPath .= $fieldData->getOptionValue();
                $conflict = 'replaced';
                break;
              case FieldDataType::DB_FILE:
                $dbFile = $this->getDatabaseRepository(Entities\EncryptedFile::class)
                               ->find($fieldData->getOptionValue());
                if (empty($dbFile)) {
                  return self::grumble($this->l->t('Unable to find associated file with id "%s" in data-base.',
                                                   $fieldData->getOptionValue()));
                }
                $conflict = 'replaced';
                break;
              case FieldDataType::CLOUD_FOLDER:
                $optionValue = json_decode($fieldData->getOptionValue(), true);
                if (!is_array($optionValue)) {
                  $optionValue = [];
                }
                if (array_search($pathInfo['basename'], $optionValue) !== false) {
                  $conflict = 'replaced';
                }
                break;
              }
              break;
            }
          }

          $fileData = $this->getUploadContent($file);

          switch ($dataType) {
          case FieldDataType::CLOUD_FILE:
          case FieldDataType::CLOUD_FOLDER:
            // @todo perhaps just store the extension as the rest is derived
            // from the field-name and user-id-slug
            $optionValue = $pathInfo['basename'];
            if ($dataType == FieldDataType::CLOUD_FOLDER) {
              $oldValue = json_decode($fieldData->getOptionValue(), true);
              if (!is_array($oldValue)) {
                $oldValue = [];
              }
              $oldValue[] = $optionValue;
              $optionValue = array_unique($oldValue);
              sort($optionValue);
              $optionValue = json_encode(array_values($optionValue));
            }
            $fieldData->setOptionValue($optionValue);
            $this->persist($fieldData);
            if ($oldPath && $oldPath != $filePath) {
              // try to rename first (extension can be different) in order to
              // have a file history inside the cloud.
              try {
                $userStorage->rename($oldPath, $filePath);
              } catch (\Throwable $t) {
                $messages[] = $this->l->t('Unable to rename the old file "%1$s" to the new name "%2$s".
 There will be no undo-history in the cloud file-space for this file.', [ $oldPath, $filePath ]);
              }
            }
            $userStorage->putContent($filePath, $fileData);
            $downloadLink = $userStorage->getDownloadLink($filePath);
            break;
          case FieldDataType::DB_FILE:
            /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
            $mimeTypeDetector = $this->di(\OCP\Files\IMimeTypeDetector::class);
            $mimeTyppe = $mimeTypeDetector->detectString($fileData);

            if (empty($dbFile)) {
              /** @var Entities\EncryptedFile $dbFilew */
              $dbFile = new Entities\EncryptedFile(
                fileName: $filePath,
                data: $fileData,
                mimeType: $mimeType,
                owner: $musician
              );
            } else {
              $dbFile
                ->setFileName($filePath)
                ->setSize(strlen($fileData))
                ->setMimeType($mimeType)
                ->getFileData()->setData($fileData);
            }

            $this->persist($dbFile);
            $this->flush();
            $fieldData->setOptionValue($dbFile->getId());
            $this->persist($fieldData);

            $downloadLink = $this->urlGenerator()->linkToRoute($this->appName().'.downloads.get', [
              'section' => 'database',
              'object' => $dbFile->getId(),
            ])
              . '?requesttoken=' . urlencode(\OCP\Util::callRegister())
              . '&fileName=' . urlencode($filePath);

            break;
          }

          $fileCopied = true;
          $this->removeStashedFile($file);

          unset($file['tmp_name']);
          $file['message'] = $this->l->t('Upload of "%s" as "%s" successful.',
                                         [ $file['name'], $filePath ]);
          $file['name'] = $filePath;

          // ensure the per-participant folder exists, even for data-base
          // uploads as it contains a mount-point in this case.
          $this->projectService->ensureParticipantFolder($project, $musician, dry: false);

          $file['meta'] = [
            'musicianId' => $musicianId,
            'projectId' => $projectId,
            // 'pathChain' => $pathChain, ?? needed ??
            'dirName' => $pathInfo['dirname'],
            'baseName' => $pathInfo['basename'],
            'extension' => $pathInfo['extension']?:'',
            'fileName' => $pathInfo['filename'],
            'download' => $downloadLink,
            'conflict' => $conflict,
            'messages' => $messages,
          ];

          $this->flush();
          $this->entityManager->commit();
        } catch (\Throwable $t) {
          $this->entityManager->rollback();
          if ($fileCopied) {
            switch ($dataType) {
            case FieldDataType::CLOUD_FILE:
              // @todo HERE WE SHOULD RESTORE THE OLD FILE IF ANY
              // unlink the new file
              $userStorage->delete($filePath);
              break;
            case FieldDataType::DB_FILE:
              // should be handled by roll-back automatically
              break;
            }
          }
          throw new \RuntimeException($this->l->t('Unable to store uploaded data'), $t->getCode(), $t);
        }
        $uploads[] = $file;
      }
      return self::dataResponse($uploads);
    default:
      break;
    }
    return self::grumble($this->l->t('Unknown Request: "%s"', $operation));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
