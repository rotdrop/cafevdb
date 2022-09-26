<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;

use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Storage\DatabaseStorageUtil;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;

/** File related per-participant-fields. */
trait ParticipantFileFieldsTrait
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Storage\Database\ProjectParticipantsStorageTrait;
  use ParticipantFieldsCgiNameTrait;

  /** @var ProjectService */
  protected $projectService;

  /** @var ProjectParticipantFieldsService */
  protected $participantFieldsService;

  /** @var UserStorage */
  protected $userStorage;

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var PHPMyEdit */
  protected $pme;

  /** @var string */
  protected static $toolTipsPrefix = 'page-renderer:participant-fields:display';

  /** Generate one HTML input row for a cloud-file field. */
  protected function cloudFileUploadRowHtml($optionValue, $fieldId, $optionKey, $subDir, $fileBase, $musician)
  {
    $project = ($project ?? $this->project) ?? null;
    $participantFolder = $this->projectService->ensureParticipantFolder($project, $musician);
    // make sure $subDir exists
    if (!empty($subDir)) {
      $subDirPrefix = UserStorage::PATH_SEP . $subDir;
      // $this->userStorage->ensureFolder($participantFolder . $subDirPrefix);
    } else {
      $subDirPrefix = '';
    }
    if (!empty($fileBase)) {
      $fileName = $this->projectService->participantFilename($fileBase, $musician);
      $placeHolder = $this->l->t('Load %s', $fileName);
      if (!empty($optionValue)) {
        $fileName .= '.' . pathinfo($optionValue, PATHINFO_EXTENSION);
      }
    } else {
      $fileName = $optionValue;
      $placeHolder = $this->l->t('Drop files here or click to upload fileds.');
    }
    if (!empty($optionValue)) {
      $filePath = $participantFolder . $subDirPrefix . UserStorage::PATH_SEP . $fileName;
      try {
        $downloadLink = $this->userStorage->getDownloadLink($filePath);
      } catch (\OCP\Files\NotFoundException $e) {
        $downloadLink = '#';
        $optionValue = '<span class="error tooltip-auto" title="' . $filePath . '">' . $this->l->t('The file "%s" could not be found on the server.', $fileName) . '</span>';
      }
    } else {
      $downloadLink = '';
    }

    $filesAppLinkParticipant = $this->userStorage->getFilesAppLink($participantFolder);
    $filesAppTarget = md5($filesAppLinkParticipant);
    $filesAppPath = $participantFolder . $subDirPrefix;
    try {
      $filesAppLink = $this->userStorage->getFilesAppLink($filesAppPath, true);
    } catch (\OCP\Files\NotFoundException $e) {
      $filesAppLink = $filesAppLinkParticipant;
    }
    $optionValueName = $this->pme->cgiDataName(self::participantFieldValueFieldName($fieldId))
                     . ($subDir ? '[]' : '');

    return (new TemplateResponse(
      $this->appName(),
      'fragments/participant-fields/cloud-file-upload-row', [
        'fieldId' => $fieldId,
        'optionKey' => $optionKey,
        'optionValue' => $optionValue,
        'musicianId' => $musician->getId(),
        'projectId' => empty($project) ? 0 : $project->getId(),
        'subDir' => $subDir,
        'fileBase' => $fileBase,
        'fileName' => $fileName,
        'participantFolder' => $participantFolder,
        'filesAppPath' => $filesAppPath,
        'filesAppLink' => $filesAppLink,
        'filesAppTarget' => $filesAppTarget,
        'downloadLink' => $downloadLink,
        'optionValueName' => $optionValueName,
        'uploadPlaceHolder' => $placeHolder,
        'toolTips' => $this->toolTipsService,
        'toolTipsPrefix' => self::$toolTipsPrefix,
      ],
      'blank'
    ))->render();
  }

  protected function dbFileUploadRowHtml($optionValue, int $fieldId, string $optionKey, ?string $subDir, ?string $fileBase, Entities\Musician $musician, ?Entities\Project $project = null, bool $overrideFileName = false)
  {
    $project = ($project ?? $this->project) ?? null;
    $participantFolder = empty($project)
      ? ''
      : $this->projectService->ensureParticipantFolder($project, $musician, dry: true);
    $subDirPrefix = UserStorage::PATH_SEP . $this->getDocumentsFolderName();
    if (!empty($subDir)) {
      $subDirPrefix .= UserStorage::PATH_SEP . $subDir;
    }
    if (!empty($optionValue)) {
      /** @var Entities\File $file */
      $file = $this->findEntity(Entities\File::class, $optionValue);
      $dbPathName = $file->getFileName();
      if (!empty($dbPathName)) {
        $dbPathInfo = pathinfo($dbPathName);
        $dbFileName = $dbPathInfo['basename'];
        $dbExtension = $dbPathInfo['extension'];
      } else {
        $dbExtension = Util::fileExtensionFromMimeType($file->getMimeType());
        $dbFileName = $fileBase . '.' . $dbExtension;
      }
    }
    if ($overrideFileName) {
      $fileName = $fileBase;
      $extension = pathinfo($fileName, PATHINFO_EXTENSION);
      if (empty($extension) && !empty($dbExtension)) {
        $fileName .= '.' . $dbExtension;
      }
    } else if (!empty($fileBase)) {
      if (empty($project)) {
        throw new \RuntimeException($this->l->t('No project given, unable generate a file-name.'));
      }
      $fileName = $this->projectService->participantFilename($fileBase, $musician);
      if (!empty($dbExtension)) {
        $fileName .= '.' . $dbExtension;
      }
    } else if (!empty($optionValue)) {
      $fileName = $dbFileName;
    } else {
      $fileName = null;
    }
    if (!empty($optionValue)) {
      $downloadLink = $this->di(DatabaseStorageUtil::class)->getDownloadLink(
        $optionValue, $fileName);
    } else {
      $downloadLink = $dbFileName = $dbExtension = '';
    }
    if (!empty($participantFolder)) {
      $filesAppLinkParticipant = $this->userStorage->getFilesAppLink($participantFolder, subDir: true);
      $filesAppTarget = md5($filesAppLinkParticipant);
      $filesAppPath = $participantFolder . $subDirPrefix;
      try {
        $filesAppLink = $this->userStorage->getFilesAppLink($filesAppPath, true);
      } catch (\OCP\Files\NotFoundException $e) {
        $this->logInfo('No file found for ' . $filesAppPath);
        $filesAppLink = $filesAppLinkParticipant;
      }
    } else {
      $filesAppPath = '';
      $filesAppLink = '';
    }
    $placeHolder = empty($fileBase)
      ? $this->l->t('Drop files here or click to upload files.')
      : $this->l->t('Load %s', pathinfo($fileName, PATHINFO_FILENAME));
    $optionValueName = $this->pme->cgiDataName(self::participantFieldValueFieldName($fieldId));

    return (new TemplateResponse(
      $this->appName(),
      'fragments/participant-fields/db-file-upload-row', [
        'fieldId' => $fieldId,
        'optionKey' => $optionKey,
        'optionValue' => $optionValue,
        'musicianId' => $musician->getId(),
        'projectId' => empty($project) ? 0 : $project->getId(),
        'fileBase' => $fileBase,
        'fileName' => $fileName,
        'participantFolder' => $participantFolder,
        'filesAppPath' => $filesAppPath,
        'filesAppLink' => $filesAppLink,
        'filesAppTarget' => $filesAppTarget,
        'downloadLink' => $downloadLink,
        'optionValueName' => $optionValueName,
        'uploadPlaceHolder' => $placeHolder,
        'toolTips' => $this->toolTipsService,
        'toolTipsPrefix' => self::$toolTipsPrefix,
      ],
      'blank'
    ))->render();
  }

  /**
   * Generate a link to the files-app if appropriate
   */
  private function getFilesAppLink(?Entities\ProjectParticipantField $field, Entities\Musician $musician, ?Entities\Project $project = null, ?string $subFolder = null)
  {
    $pathChain = [];
    $project = $project ?? $this->project;
    if (!empty($field)) {
      $project = $project ?? $field->getProject();
      switch ($field->getDataType()) {
        case FieldType::SERVICE_FEE:
        case FieldType::DB_FILE:
          $pathChain[] = $this->getDocumentsFolderName();
          if ($field->getDataType() == FieldType::SERVICE_FEE) {
            $pathChain[] = $this->getSupportingDocumentsFolderName();
          }
          if ($field->getMultiplicity() != FieldMultiplicity::SIMPLE) {
            $pathChain[] = $this->participantFieldsService->getFileSystemFieldName($field);
          }
          break;
        case FieldType::CLOUD_FILE:
          if ($field->getMultiplicity() != FieldMultiplicity::SIMPLE) {
            $pathChain[] = $this->participantFieldsService->getFileSystemFieldName($field);
          }
          break;
        case FieldType::CLOUD_FOLDER:
          $pathChain[] = $this->participantFieldsService->getFileSystemFieldName($field);
          break;
        default:
          return null;
      }
    }
    if (!empty($subFolder)) {
      $pathChain[] = $subFolder;
    }
    $participantFolder = $this->projectService->ensureParticipantFolder($project, $musician, dry: true);
    array_unshift($pathChain, $participantFolder);

    $filesAppLink = null;
    $filesAppTarget = md5($this->userStorage->getFilesAppLink($participantFolder));
    while (!empty($pathChain)) {
      $folderPath = implode(UserStorage::PATH_SEP, $pathChain);
      try {
        $filesAppLink = $this->userStorage->getFilesAppLink($folderPath, true);
        break;
      } catch (/*\OCP\Files\NotFoundException*/ \Throwable $e) {
        $this->logException($e, [ 'level' => \OCP\ILogger::DEBUG ]);
        array_pop($pathChain);
      }
    }
    return [ $filesAppLink, $filesAppTarget ];
  }

  private function getFilesAppAnchor(?Entities\ProjectParticipantField $field, Entities\Musician $musician, ?Entities\Project $project = null, ?string $subFolder = null, ?string $toolTip = null)
  {
    if (!empty($toolTip)) {
      $toolTip = $this->toolTipsService['participant-attachment-open-parent'] . '<br/>' . $toolTip;
    } else {
      $toolTip = $this->toolTipsService['participant-attachment-open-parent'];
    }
    list($filesAppLink, $filesAppTarget) = $this->getFilesAppLink($field, $musician, $project, $subFolder);
    $html = '<a href="' . $filesAppLink . '" target="'.$filesAppTarget.'"
       title="' . $toolTip . '"
       class="button operation open-parent tooltip-auto'.(empty($filesAppLink) ? ' disabled' : '').'"
       ></a>';
    return $html;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
