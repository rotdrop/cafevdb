<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use \RuntimeException;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\Projects as Renderer;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Storage\Database\Factory as StorageFactory;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;

use OCA\CAFEVDB\Common;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Constants;

/** AJAX end-points for project participants */
class ProjectParticipantsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Controller\FileUploadRowTrait;
  use \OCA\CAFEVDB\Traits\FakeTranslationTrait;

  const LIST_ACTION_SUBSCRIBE = 'subscribe';
  const LIST_ACTION_UNSUBSCRIBE = 'unsubscribe';
  const LIST_ACTION_ENABLE_DELIVERY = 'enable-delivery';
  const LIST_ACTION_DISABLE_DELIVERY = 'disable-delivery';
  const LIST_ACTION_RELOAD_SUBSCRIPTION = 'reload-subscription';

  const LIST_SUBSCRIPTION_DELIVERY_ENABLED = 'delivery-enabled';
  const LIST_SUBSCRIPTION_DELIVERY_DISABLED = 'delivery-disabled';
  const LIST_SUBSCRIPTION_DISABLED_BY_USER = 'disabled-by-user';
  const LIST_SUBSCRIPTION_DISABLED_BY_BOUNCES = 'disabled-by-bounces';
  const LIST_SUBSCRIPTION_DISABLED_BY_MODERATOR = 'disabled-by-moderator';
  const LIST_SUBSCRIPTION_MODE_DIGEST = 'mode-digest';

  const FILE_ACTION_DELETE = 'delete';
  const FILE_ACTION_UPLOAD = 'upload';

  /** @var \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit */
  protected $pme;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var ProjectService */
  private $projectService;

  /** @var ProjectParticipantFieldsService */
  private $participantFieldsService;

  /** @var EntityManager */
  protected $entityManager;

  /** @var StorageFactory */
  private $storageFactory;

  /** {@inheritdoc} */
  public function __construct(
    $appName,
    IRequest $request,
    RequestParameterService $parameterService,
    ConfigService $configService,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
    ProjectService $projectService,
    ProjectParticipantFieldsService $participantFieldsService,
    StorageFactory $storageFactory,
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->pme = $phpMyEdit;
    $this->projectService = $projectService;
    $this->participantFieldsService = $participantFieldsService;
    $this->storageFactory = $storageFactory;
    $this->l = $this->l10N();
    $this->setDatabaseRepository(Entities\ProjectParticipant::class);
  }

  /**
   * @param int $projectId The numeric project id.
   *
   * @param null|int $musicianId The musician id to add. If empty, then the
   * legacy PME "mrecs" parameter is fetched from the request.
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function addMusicians(int $projectId, ?int $musicianId = null):Response
  {
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
        function($value) {
          $id = json_decode($value, true);
          return $id['id']??$id;
        },
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

      $messages[] = $this->l->t(
        'No musician could be added to the project, #failures: %d.',
        count($failedMusicians)
      );

      foreach ($failedMusicians as $id => $failures) {
        foreach ($failures as $failure) {
          $messages[] = ' '.$failure['notice'];
        }
      }

      return self::grumble([ 'message' => $messages ]);

    } else {

      $messages = [];
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
   * @param string $context Either of 'change-musician-instruments' or 'change-project-instruments'.
   *
   * @param array $recordId TBD.
   *
   * @param array $instrumentValues TBD.
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function changeInstruments(string $context, array $recordId = [], array $instrumentValues = []):Response
  {
    $this->logDebug($context.' / '.print_r($recordId, true).' / '.print_r($instrumentValues, true));
    if (empty($instrumentValues)) {
      $instrumentValues = [];
    }

    switch ($context) {
      case 'musician':
      case 'project':
        if (empty($recordId['projectId']) || empty($recordId['musicianId'])) {
          return self::grumble($this->l->t(
            "Project- or musician-id is missing (%s/%s)",
            [ $recordId['projectId'], $recordId['musicianId'], ]
          ));
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
          $rankingA = $a->getRanking();
          $rankingB = $b->getRanking();
          return (($rankingA === $rankingB) ? 0 : (($rankingA <= $rankingB) ? -1 : 1));
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
                return self::grumble($this->l->t(
                  'Denying the attempt to remove the instrument %s because it is used in the current project.',
                  $projectInstruments[$removedId]['instrument']['name']
                ));
              }

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
                return self::grumble($this->l->t(
                  'Denying the attempt to add an unknown instrument (id = %s)',
                  $addedId
                ));
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
                return self::grumble($this->l->t(
                  'Denying the attempt to add an unknown instrument (id = %s).',
                  $addedId
                ));
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
   * @param string $operation Either self::OPERATION_DELETE or self::OPERATION_UPLOAD.
   *
   * @param int $musicianId Musician id.
   *
   * @param int $projectId Project id.
   *
   * @param null|int $fieldId Field id, null if this is a file-upload.
   *
   * @param null|string $optionKey Option key, should be a UUID in string form, null if this is a file-upload.
   *
   * @param null|string $subDir Subdirectory to place the file in, null if this is a file upload.
   *
   * @param null|string $fileName The file name, null if this is a file-upload.
   *
   * @param null|string $data Additional upload data, JSON encoded.
   *
   * @param null|string $files Uploaded files, JSON encoded, null if this is not a file-upload.
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function files(
    string $operation,
    int $musicianId,
    int $projectId,
    ?int $fieldId,
    ?string $optionKey,
    ?string $subDir,
    ?string $fileName,
    ?string $data,
    ?string $files = null
  ):Response {
    // $upload_max_filesize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
    // $post_max_size = \OCP\Util::computerFileSize(ini_get('post_max_size'));
    // $maxUploadFileSize = min($upload_max_filesize, $post_max_size);
    // $maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);

    /** @var Entities\ProjectParticipant $participant */
    $participant = $this->getDatabaseRepository(Entities\ProjectParticipant::class)
      ->find(['project' => $projectId, 'musician' => $musicianId]);
    $project = $participant->getProject();
    $musician = $participant->getMusician();

    /** @var UserStorage $userStorage */
    $userStorage = $this->di(UserStorage::class);

    switch ($operation) {
      case self::FILE_ACTION_DELETE:
        /** @var Entities\ProjectParticipantField $field */
        $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);

        /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
        $fieldDatum = $participant->getParticipantFieldsDatum($optionKey);
        if (empty($fieldDatum)) {
          return self::grumble($this->l->t('Unable to find any data for the option key "%s".', $optionKey));
        }

        $subDirPrefix = empty($subDir) ? '' : UserStorage::PATH_SEP . $subDir;
        $fieldFolderPath = $this->participantFieldsService->doGetFieldFolderPath($field, $musician);

        $dataType = $field->getDataType();
        switch ($dataType) {
          case FieldDataType::DB_FILE:
            /** @var Entities\DatabaseStorageFile $dbDocument */
            $dbDocument = $this->getDatabaseRepository(Entities\DatabaseStorageFile::class)
              ->find($fieldDatum->getOptionValue());
            if (empty($dbDocument)) {
              return self::grumble($this->l->t(
                'Unable to find the associated file with the id "%s" in data-base.',
                $fieldDatum->getOptionValue()
              ));
            }
            $filePath = $dbDocument->getName();
            break;

          case FieldDataType::CLOUD_FILE:
            $prefixPath = $this->projectService->ensureParticipantFolder($project, $musician, dry: true);
            $filePath = $prefixPath . $subDirPrefix . UserStorage::PATH_SEP . $fieldDatum->getOptionValue();
            break;

          case FieldDataType::CLOUD_FOLDER:
            $prefixPath = $this->projectService->ensureParticipantFolder($project, $musician, dry: true);
            $filePath = $prefixPath . $subDirPrefix . UserStorage::PATH_SEP . $fileName;
            break;

          case FieldDataType::SERVICE_FEE:
            /** @var Entities\DatabaseStorageFile $dbDocument */
            $dbDocument = $fieldDatum->getSupportingDocument();
            if (empty($dbDocument)) {
              return self::grumble($this->l->t('Unable to find any supporting document for the field "%1$s", musician "%2$s".', [
                $field->getName(), $fieldDatum->getMusician()->getPublicName(),
              ]));
            }
            $filePath = $dbDocument->getName();
            break;

          default:
            return self::grumble($this->l->t('Unsupported field type "%s".', $dataType));
        }

        $doDeleteFieldDatum = true;
        $this->entityManager->beginTransaction();
        try {
          switch ($dataType) {
            case FieldDataType::CLOUD_FOLDER:
              $this->entityManager->registerPreCommitAction(
                Common\UndoableFileRemove($filePath, gracefully: true)
              );
              $optionValue = json_decode($fieldDatum->getOptionValue(), true);
              if (!is_array($optionValue)) {
                $optionValue = [];
              }
              Util::unsetValue($optionValue, $fileName);
              if (!empty($optionValue)) {
                $doDeleteFieldDatum = false;
              }
              $fieldDatum->setOptionValue(json_encode(array_values($optionValue)));
              break;
            case FieldDataType::CLOUD_FILE:
              $this->entityManager->registerPreCommitAction(
                new Common\UndoableFileRemove($filePath, gracefully: true)
              );
              if ($field->getMultiplicity() != FieldMultiplicity::SINGLE) {
                // gracefully try to remove the folder if it is empty
                $this->entityManager->registerPreCommitAction(
                  new Common\UndoableFolderRemove($fieldFolderPath, gracefully: true, recursively: false)
                );
              }
              $fieldDatum->setOptionValue(null);
              break;
            case FieldDataType::DB_FILE:
              $this->remove($dbDocument);
              $fieldDatum->setOptionValue(null);
              break;
            case FieldDataType::SERVICE_FEE:
              $fieldDatum->setSupportingDocument(null);
              $this->remove($dbDocument);
              $doDeleteFieldDatum = false;
              break;
          }
          $this->flush(); // cope with soft-delete
          if ($doDeleteFieldDatum) {
            $field->getFieldData()->removeElement($fieldDatum);
            $this->remove($fieldDatum, hard: true, flush: true);
          }
          $this->flush();
          $this->entityManager->commit();
        } catch (\Throwable $t) {
          $this->entityManager->rollback();
          throw new RuntimeException($this->l->t('Unable to delete file "%s".', $filePath), $t->getCode(), $t);
        }
        return self::response($this->l->t('Successfully removed file "%s".', $filePath));
        break;

      case self::FILE_ACTION_UPLOAD:

        $uploadData = json_decode($data, true);
        $fieldId = $uploadData['fieldId'];
        $optionKey = $uploadData['optionKey'];
        $subDir = $uploadData['subDir']??null;
        $fileName = $uploadData['fileName']??null;
        $filesAppPath = $uploadData['filesAppPath']??null;

        $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->find($fieldId);
        $dataType = $field->getDataType();
        $multiplicity = $field->getMultiplicity();

        $folderPath = $filePath = '';

        switch ($dataType) {
          case FieldDataType::CLOUD_FOLDER:
          case FieldDataType::CLOUD_FILE:
            $pathChain = [ $this->projectService->ensureParticipantFolder($project, $musician, dry: true), ];
            if ($subDir) {
              $pathChain[] = $subDir;
              $subDir .= UserStorage::PATH_SEP;
            }
            $userStorage->ensureFolderChain($pathChain);
            $folderPath = implode(UserStorage::PATH_SEP, $pathChain);
            if ($dataType === FieldDataType::CLOUD_FILE) {
              $pathChain[] = $this->projectService->participantFilename($uploadData['fileBase'], $musician);
            } elseif (!empty($fileName)) {
              $pathChain[] = pathinfo($fileName, PATHINFO_FILENAME);
            }
            $filePath = implode(UserStorage::PATH_SEP, $pathChain);
            break;
          case FieldDataType::DB_FILE:
          case FieldDataType::SERVICE_FEE:
            if (!empty($subDir)) {
              return self::grumble($this->l->t('Sub-directory "%s" requested, but not supported by db-storage.', $subDir));
            }
            if (!empty($uploadData['fileBase'])) {
              $filePath = $this->projectService->participantFilename($uploadData['fileBase'], $musician);
            } elseif (!empty($fileName)) {
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

        // ensure the per-participant folder exists, even for data-base
        // uploads as it contains a mount-point in this case.
        $this->projectService->ensureParticipantFolder($project, $musician, dry: false);

        $uploads = [];
        foreach ($files as $file) {

          $messages = []; // messages for non-fatal errors

          if ($file['error'] != UPLOAD_ERR_OK) {
            $this->logInfo('Upload error ' . print_r($file, true));
            continue;
          }

          switch ($dataType) {
            case FieldDataType::CLOUD_FOLDER:
              if (empty($fileName)) {
                // use original name as storage name in the cloud
                $filePath = $folderPath . UserStorage::PATH_SEP . pathinfo($file['name'], PATHINFO_FILENAME);
              }
              break;
            case FieldDataType::DB_FILE:
            case FieldDataType::SERVICE_FEE:
              if (empty($filePath)) {
                $filePath = pathinfo($file['name'], PATHINFO_FILENAME);
              }
              break;
            default:
              break; // ok, resp. cannot happen
          }

          $originalFilePath = $file['original_name'] ?? null;

          $uploadMode = $file['upload_mode'] ?? UploadsController::UPLOAD_MODE_COPY;

          switch ($uploadMode) {
            case UploadsController::UPLOAD_MODE_MOVE:
              if (empty($originalFilePath)) {
                return self::grumble($this->l->t('Move operation requested, but the original file path has not been specified.'));
              }
              $originalFile = $userStorage->get($originalFilePath);
              if (empty($originalFile)) {
                return self::grumble($this->l->t('Move operation requested, but the original file "%s" cannot be found.', $originalFilePath));
              }
              break;
            case UploadsController::UPLOAD_MODE_LINK:
              if ($dataType != FieldDataType::DB_FILE && $dataType != FieldDataType::SERVICE_FEE) {
                return self::grumble($this->l->t('Link operation requested, but the link target does not reside in the database storage.'));
              }
              $originalFileId = $file['original_name'];
              if (empty($originalFileId)) {
                return self::grumble($this->l->t('Link operation requested, but the id of the original file has not been specified.'));
              }
              $originalFile = $this->entityManager->find(Entities\File::class, $originalFileId);
              if (empty($originalFile)) {
                return self::grumble($this->l->t('Link operation requested, but the existing original file with id "%s" cannot be found.', $originalFileId));
              }
              $originalFilePath = $originalFile->getFileName();
              break;
            case UploadsController::UPLOAD_MODE_COPY:
              // this is the default, nothing special
              break;
          }

          $originalFileName = $originalFilePath ? basename($originalFilePath) : null;

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
              $this->persist($fieldData);
            } else {
              $fieldData->setDeleted(null);
            }

            $optionValue = $fieldData->getOptionValue();
            $dbDocument = null;
            $dbFile = null;
            switch ($dataType) {
              case FieldDataType::CLOUD_FILE:
                if (empty($optionValue)) {
                  break;
                }
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
                if (empty($optionValue)) {
                  break;
                }
                $dbDocument = $this->getDatabaseRepository(Entities\DatabaseStorageFile::class)
                  ->find($fieldData->getOptionValue());
                if (empty($dbDocument)) {
                  return self::grumble($this->l->t(
                    'Unable to find the associated file with the id "%s" in data-base.',
                    $fieldData->getOptionValue()
                  ));
                }
                $dbFile = $dbDocument->getFile();
                $conflict = 'replaced';
                break;
              case FieldDataType::SERVICE_FEE:
                $dbDocument = $fieldData->getSupportingDocument();
                if (!empty($dbDocument)) {
                  $conflict = 'replaced';
                  $dbFile = $dbDocument->getFile();
                }
                break;
              case FieldDataType::CLOUD_FOLDER:
                if (empty($optionValue)) {
                  break;
                }
                $optionValue = json_decode($fieldData->getOptionValue(), true);
                if (!is_array($optionValue)) {
                  $optionValue = [];
                }
                if (array_search($pathInfo['basename'], $optionValue) !== false) {
                  $conflict = 'replaced';
                }
                break;
            }

            switch ($uploadMode) {
              case UploadsController::UPLOAD_MODE_MOVE:
                $this->entityManager->registerPreCommitAction(new Common\UndoableFileRemove($originalFilePath, gracefully: true));
                // no break
              case UploadsController::UPLOAD_MODE_COPY:
                $fileData = $this->getUploadContent($file);
                break;
              case UploadsController::UPLOAD_MODE_LINK:
                $fileData = null;
                /** @var Entities\EncryptedFile $originalFile */
                if (!empty($dbFile) && $dbFile->getId() == $originalFileId) {
                  return self::grumble($this->l->t('Link operation requested, but the existing original file is the same as the target destination (%s@%s)', [
                    $originalFile->getFileName(), $originalFileId
                  ]));
                }
                break;
            }

            switch ($dataType) {
              case FieldDataType::CLOUD_FILE:
              case FieldDataType::CLOUD_FOLDER:
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
                $this->entityManager->registerPreCommitAction(
                  new Common\UndoableFileReplace($filePath, $fileData, $oldPath, gracefully: true)
                );
                if ($dataType == FieldDataType::CLOUD_FOLDER || $multiplicity == FieldMultiplicity::PARALLEL) {
                  $readMe = Util::htmlToMarkDown($field->getTooltip());
                  // also place the tooltip as README.md
                  $this->entityManager->registerPreCommitAction(
                    new Common\UndoableTextFileUpdate(
                      basename($filePath) . Constants::PATH_SEP . Constants::README_NAME,
                      content: $readMe,
                      gracefully: true,
                    )
                  );
                }
                $this->entityManager->registerPreCommitAction(function() use (&$file, $filePath, $userStorage) {
                  $downloadLink = $userStorage->getDownloadLink($filePath);
                  $file['meta']['download'] = $downloadLink;
                });
                break;
              case FieldDataType::SERVICE_FEE:
              case FieldDataType::DB_FILE:

                $storage = $this->storageFactory->getProjectParticipantsStorage($participant);

                switch ($uploadMode) {
                  case UploadsController::UPLOAD_MODE_COPY:
                  case UploadsController::UPLOAD_MODE_MOVE:
                    // replace the data or generate a new document

                    /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
                    $mimeTypeDetector = $this->di(\OCP\Files\IMimeTypeDetector::class);
                    $mimeType = $mimeTypeDetector->detectString($fileData);

                    if (!empty($dbFile) && $dbFile->getFile()->getNumberOfLinks() > 1) {
                      // if the file has multiple links then it is probably
                      // better to remove the existing file rather than
                      // overwriting a file which has multiple links.
                      $dbFile->setFile(null);
                      $dbFile = null;
                    }

                    if (empty($dbFile)) {
                      /** @var Entities\EncryptedFile $dbFilew */
                      $dbFile = new Entities\EncryptedFile(
                        fileName: $filePath,
                        data: $fileData,
                        mimeType: $mimeType,
                        owner: $musician
                      );
                      $this->persist($dbFile);
                    } else {
                      $dbFile
                        ->setFileName($filePath)
                        ->setSize(strlen($fileData))
                        ->setMimeType($mimeType)
                        ->getFileData()->setData($fileData);
                    }
                    $dbFile->setFileName($originalFileName ?? null);
                    break;
                  case UploadsController::UPLOAD_MODE_LINK:
                    $dbFile = $originalFile;
                    break;
                }

                if (!empty($dbDocument)) {
                  $dbDocument->setFile($dbFile);
                } else {
                  $dbDocument = $storage->addFieldDatumDocument($fieldData, $dbFile, flush: false);
                }
                $this->flush();

                if ($dataType == FieldDataType::DB_FILE) {
                  $fieldData->setOptionValue($dbDocument->getId());
                } else {
                  $fieldData->setSupportingDocument($dbDocument);
                }

                $downloadLink = $this->urlGenerator()->linkToRoute($this->appName().'.downloads.get', [
                  'section' => 'database',
                  'object' => $dbDocument->getId(),
                ])
                  . '?requesttoken=' . urlencode(\OCP\Util::callRegister())
                  . '&fileName=' . urlencode($filePath);

                break;
            }

            $filesAppLink = '';
            try {
              if (!empty($filesAppPath)) {
                $filesAppLink = $userStorage->getFilesAppLink($filesAppPath, true);
              }
            } catch (\Throwable $t) {
              $this->logException($t, 'Unable to get files-app link for ' . $filesAppPath);
            }

            if ($uploadMode != UploadsController::UPLOAD_MODE_LINK) {
              $fileCopied = true;
              $this->removeStashedFile($file);
            }

            unset($file['tmp_name']);
            switch ($uploadMode) {
              case UploadsController::UPLOAD_MODE_COPY:
                $messages[] = $this->l->t('Upload of "%s" as "%s" successful.', [ $file['name'], $filePath ]);
                break;
              case UploadsController::UPLOAD_MODE_MOVE:
                $messages[] = $this->l->t('Move of "%s" to "%s" successful.', [ $originalFilePath, $filePath ]);
                break;
              case UploadsController::UPLOAD_MODE_LINK:
                $messages[] = $this->l->t('Linking of file id "%s" to "%s" successful.', [ $originalFileId, $filePath ]);
                break;
            }
            $file['name'] = $filePath;

            $file['meta'] = [
              'musicianId' => $musicianId,
              'projectId' => $projectId,
              'dirName' => $pathInfo['dirname'],
              'baseName' => $pathInfo['basename'],
              'extension' => $pathInfo['extension']?:'',
              'fileName' => $pathInfo['filename'],
              'download' => $downloadLink ?? null,
              'filesApp' => $filesAppLink,
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
                  // should have been handled by the undoable pre-commit actions
                  // $userStorage->delete($filePath);
                  break;
                case FieldDataType::DB_FILE:
                case FieldDataType::SERVICE_FEE:
                  // should be handled by roll-back automatically
                  break;
              }
            }
            throw new RuntimeException($this->l->t('Unable to store uploaded data'), $t->getCode(), $t);
          }
          $uploads[] = $file;
        }
        return self::dataResponse($uploads);
      default:
        break;
    }
    return self::grumble($this->l->t('Unknown Request: "%s"', $operation));
  }

  /**
   * @param string $operation The operation to perform, see LIST_ACTION defines in this class.
   *
   * @param int $projectId Project id.
   *
   * @param int $musicianId Musician id.
   *
   * @param bool $force Enforce the operation.
   *
   * @return OCP\AppFramework\Http\Response
   *
   * @NoAdminRequired
   */
  public function mailingListSubscriptions(
    string $operation,
    int $projectId,
    int $musicianId,
    bool $force = false,
  ) {
    /** @var MailingListsService $listsService */
    $listsService = $this->di(MailingListsService::class);
    if (!$listsService->isConfigured()) {
      return self::grumble($this->l->t('Mailing-lists REST API is not configured.'));
    }

    $participant = $this->projectService->findParticipant($projectId, $musicianId);
    if (empty($participant)) {
      return self::grumble($this->l->t('Unable to find the participant by project- and musician-id "%1$d / %2$d".', [ $projectId, $musicianId ]));
    }

    /** @var Entities\Project $project */
    $project = $participant->getProject();
    $listId = $project->getMailingListId();
    if (empty($listId)) {
      return self::grumble($this->l->t('The project "%s" does not yet have a mailing-list.', $project->getName));
    }

    /** @var Entities\Musician $musician */
    $musician = $participant->getMusician();
    $email = $musician->getEmail();
    if (empty($email)) {
      return self::grumble($this->l->t('The musician "%s" does not have an email-address.', $musician->getPublicName()));
    }

    $subscriptionStatus = $listsService->getSubscriptionStatus($listId, $email);
    if ($subscriptionStatus == MailingListsService::STATUS_SUBSCRIBED) {
      // $subscription = $listsService->getSubscription($listId, $email);
      $preferences = $listsService->getSubscriptionPreferences($listId, $email);
      $deliveryStatus = $preferences[MailingListsService::MEMBER_DELIVERY_STATUS];
      // $deliveryMode = $subscription[MailingListsService::ROLE_MEMBER][MailingListsService::MEMBER_DELIVERY_MODE];
    }

    $messages = [];
    switch ($operation) {
      case self::LIST_ACTION_SUBSCRIBE:
        if ($subscriptionStatus == MailingListsService::STATUS_SUBSCRIBED) {
          return self::dataResponse([ 'status' => 'unchanged' ]);
        }
        if (!$force && empty($participant->getRegistration())) {
          return self::dataResponse([
            'status' => 'unconfirmed',
            'feedback' => $this->l->t(
              '%1$s participation has not been confirmed yet.'
              . ' Please consider to set the participation status to "confirmed" which also will subscribe %1$s to the project list'
              . ' and will send a notification to the participant.'
              . ' Are you sure that you want to subscribe an unconfirmed participant to the project mailing list?', [
                $musician->getPublicName(firstNameFirst: true)
              ]),
          ]);
        }
        $this->projectService->ensureMailingListSubscription($participant);
        $messages[] = $this->l->t('%1$s <%2$s> has been subscribed to %3$s.', [
          $musician->getPublicName(firstNameFirst: true), $email, $listId
        ]);
        $messages[] = $this->l->t('A notification has been sent to %1$s <%2$s>.', [
          $musician->getPublicName(firstNameFirst: true), $email
        ]);
        break;
      case self::LIST_ACTION_UNSUBSCRIBE:
        if ($subscriptionStatus != MailingListsService::STATUS_SUBSCRIBED) {
          return self::dataResponse([ 'status' => 'unchanged' ]);
        }
        if (!$force && !empty($participant->getRegistration())) {
          return self::dataResponse([
            'status' => 'unconfirmed',
            'feedback' => $this->l->t('The participation of %1$s has already been been confirmed. Are you really really sure that want to unsubscribe %1$s from the project mailing list?', [
              $musician->getPublicName(firstNameFirst: true)
            ]),
          ]);
        }
        $this->projectService->ensureMailingListUnsubscription($participant);
        $messages[] = $this->l->t('%1$s <%2$s> has been unsubscribed from %3$s.', [
          $musician->getPublicName(firstNameFirst: true), $email, $listId
        ]);
        $messages[] = $this->l->t('A notification has been sent to %1$s <%2$s>.', [
          $musician->getPublicName(firstNameFirst: true), $email
        ]);
        break;
      case self::LIST_ACTION_ENABLE_DELIVERY:
        if ($deliveryStatus == MailingListsService::DELIVERY_STATUS_ENABLED) {
          return self::dataResponse([ 'status' => 'unchanged' ]);
        }
        if (!$force) {
          return self::dataResponse([
            'status' => 'unconfirmed',
            'feedback' => $this->l->t('%1$s can enable message delivery by itself. Are you really sure that you want to enable message delivery for %1$s?', [
              $musician->getPublicName(firstNameFirst: true)
            ]),
          ]);
        }
        $listsService->setSubscriptionPreferences($listId, $email, preferences: [
          MailingListsService::MEMBER_DELIVERY_STATUS => MailingListsService::DELIVERY_STATUS_ENABLED,
        ]);
        $messages[] = $this->l->t('Re-enabled message delivery for %1$s <%2$s>.', [
          $musician->getPublicName(firstNameFirst: true), $email
        ]);
        break;
      case self::LIST_ACTION_DISABLE_DELIVERY:
        if ($deliveryStatus == MailingListsService::DELIVERY_STATUS_DISABLED_BY_USER) {
          return self::dataResponse([ 'status' => 'unchanged' ]);
        }
        if (!$force) {
          return self::dataResponse([
            'status' => 'unconfirmed',
            'feedback' => $this->l->t('%1$s can disable message delivery by itself. Are you really sure that you want to disable message delivery for %1$s?', [
              $musician->getPublicName(firstNameFirst: true)
            ]),
          ]);
        }
        // set to "disabled by user" s.t. the victim can re-enable it again by itself
        $listsService->setSubscriptionPreferences($listId, $email, preferences: [
          MailingListsService::MEMBER_DELIVERY_STATUS => MailingListsService::DELIVERY_STATUS_DISABLED_BY_USER,
        ]);
        $messages[] = $this->l->t('Disabled message delivery for %1$s <%2$s>.', [
          $musician->getPublicName(firstNameFirst: true), $email
        ]);
        break;
      case self::LIST_ACTION_RELOAD_SUBSCRIPTION:
        // just fall through to the status query.
        break;
      default:
        return self::grumble($this->l->t('Unknown list action: "%s".', $operation));
    }

    // after performing the actions query the REST service again about the status
    $summary = self::mailingListDeliveryStatus($listsService, $listId, $email);

    $summary['status'] = 'success';
    $summary['message'] = $messages;
    return self::dataResponse($summary);
  }

  /**
   * Fetch the delivery states for the given email. Delivery may be disabled
   * by the list-member in which case the email-form has to send its messages
   * separately to this member if needed.
   *
   * @param MailingListsService $listsService Mailing lists management class.
   *
   * @param string $listId The list id to work on.
   *
   * @param string $email The email address of the list-member.
   *
   * @return array
   * ```
   * [
   *   'subscriptionStatus' => STATUS,
   *   'summary' => DISPLAY_STATUS, // for UI
   *   'statusTags' => STATUS_TAGS, // mode-digest etc.
   * ];
   * ```
   */
  public static function mailingListDeliveryStatus(MailingListsService $listsService, string $listId, string $email):array
  {
    $status = $listsService->getSubscriptionStatus($listId, $email);
    $displayStatus = $status;
    $statusFlags = [];
    $statusFlags[] = 'status-' . $status;
    switch ($status) {
      case MailingListsService::STATUS_SUBSCRIBED:
        $subscription = $listsService->getSubscription($listId, $email);
        $preferences = $listsService->getSubscriptionPreferences($listId, $email);

        // \OCP\Util::writeLog('cafevdb', 'SUBSCRIPTION ' . print_r($subscription, true), \OCP\Util::INFO);
        \OCP\Util::writeLog('cafevdb', 'PREFERENCES ' . print_r($preferences, true), \OCP\Util::INFO);

        switch ($preferences[MailingListsService::MEMBER_DELIVERY_STATUS]) {
          case MailingListsService::DELIVERY_STATUS_ENABLED:
            $deliveryMode = $subscription[MailingListsService::ROLE_MEMBER][MailingListsService::MEMBER_DELIVERY_MODE];
            $statusFlags[] = ProjectParticipantsController::LIST_SUBSCRIPTION_DELIVERY_ENABLED;
            $statusFlags[] = 'mode-' . $deliveryMode;
            switch ($deliveryMode) {
              case MailingListsService::DELIVERY_MODE_REGULAR:
                break;
              case MailingListsService::DELIVERY_MODE_PLAINTEXT_DIGESTS:
              case MailingListsService::DELIVERY_MODE_MIME_DIGESTS:
              case MailingListsService::DELIVERY_MODE_SUMMARY_DIGESTS:
                self::t($displayStatus = 'digest');
                $statusFlags[] = 'mode-digest';
                break;
            }
            break;
          case MailingListsService::DELIVERY_STATUS_DISABLED_BY_USER:
            $statusFlags[] = ProjectParticipantsController::LIST_SUBSCRIPTION_DELIVERY_DISABLED;
            $statusFlags[] = ProjectParticipantsController::LIST_SUBSCRIPTION_DISABLED_BY_USER;
            self::t($displayStatus = 'disabled by user');
            break;
          case MailingListsService::DELIVERY_STATUS_DISABLED_BY_BOUNCES:
            $statusFlags[] = ProjectParticipantsController::LIST_SUBSCRIPTION_DELIVERY_DISABLED;
            $statusFlags[] = ProjectParticipantsController::LIST_SUBSCRIPTION_DISABLED_BY_BOUNCES;
            self::t($displayStatus = 'disabled because of bounces');
            break;
          case MailingListsService::DELIVERY_STATUS_DISABLED_BY_MODERATOR:
            $statusFlags[] = ProjectParticipantsController::LIST_SUBSCRIPTION_DELIVERY_DISABLED;
            $statusFlags[] = ProjectParticipantsController::LIST_SUBSCRIPTION_DISABLED_BY_MODERATOR;
            self::t($displayStatus = 'disabled by moderator');
            break;
        }
        break;
      case MailingListsService::STATUS_UNSUBSCRIBED:
      case MailingListsService::STATUS_INVITED:
      case MailingListsService::STATUS_WAITING:
        // just use the given status
        break;
    }
    return [
      'subscriptionStatus' => $status,
      'summary' => $displayStatus,
      'statusTags' => $statusFlags,
    ];
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
