<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use RuntimeException;

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

use OCA\CAFEVDB\Common\Util;

/** File related per-participant-fields. */
trait ParticipantFileFieldsTrait
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait;
  use ParticipantFieldsCgiNameTrait;

  /** @var ProjectService */
  protected ProjectService $projectService;

  /** @var ProjectParticipantFieldsService */
  protected ProjectParticipantFieldsService $participantFieldsService;

  /** @var UserStorage */
  protected ?UserStorage $userStorage;

  /** @var ToolTipsService */
  protected ToolTipsService $toolTipsService;

  /** @var PHPMyEdit */
  protected PHPMyEdit $pme;

  /** @var string */
  protected static $toolTipsPrefix = 'page-renderer:participant-fields:display';

  /**
   * Generate one HTML input row for a cloud-file field.
   *
   * @param mixed $optionValue
   *
   * @param int $fieldId
   *
   * @param null|string $optionKey
   *
   * @param null|string $subDir
   *
   * @param null|string $fileBase
   *
   * @param Entities\Musician $musician
   *
   * @return string HTML fragment.
   */
  protected function cloudFileUploadRowHtml(mixed $optionValue, int $fieldId, ?string $optionKey, ?string $subDir, ?string $fileBase, Entities\Musician $musician):string
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

  /**
   * @param mixed $optionValue
   *
   * @param int $fieldId
   *
   * @param string $optionKey
   *
   * @param null|string $subDir
   *
   * @param null|string $fileBase
   *
   * @param null|Entities\Musician $musician
   *
   * @param null|Entities\Project $project
   *
   * @param bool $overrideFileName
   *
   * @param null|string $inputValueName
   *
   * @return string HTML fragment.
   */
  protected function dbFileUploadRowHtml(
    mixed $optionValue,
    int $fieldId,
    string $optionKey,
    ?string $subDir,
    ?string $fileBase,
    ?Entities\Musician $musician,
    ?Entities\Project $project = null,
    bool $overrideFileName = false,
    ?string $inputValueName = null,
  ):string {
    $filePathInfo = pathinfo($fileBase);
    $fileDirName = trim($filePathInfo['dirname'] ?? '', '.' . UserStorage::PATH_SEP);
    $fileBase = $filePathInfo['basename'];
    $project = $project ?? ($this->project ?? null);
    $folderPath = (empty($project) || empty($musician))
      ? ($overrideFileName ? UserStorage::PATH_SEP . $fileDirName : '')
      : $this->projectService->ensureParticipantFolder($project, $musician, dry: true);
    $subDirPrefix = ($overrideFileName && !empty($fileDirName))
      ? ''
      : UserStorage::PATH_SEP . $this->getDocumentsFolderName();
    if (!empty($subDir)) {
      $subDirPrefix .= UserStorage::PATH_SEP . $subDir;
    }
    if (!empty($optionValue)) {
      /** @var Entities\DatabaseStorageFile $file */
      $file = $this->findEntity(Entities\DatabaseStorageFile::class, $optionValue);
      $dbPathName = $file->getName();
      $dbPathInfo = pathinfo($dbPathName);
      $dbFileName = $dbPathInfo['basename'];
      $dbExtension = $dbPathInfo['extension'] ?? null;
      if (empty($dbExtension)) {
        $dbExtension = Util::fileExtensionFromMimeType($file->getMimeType());
      }
      if (!empty($dbExtension)) {
        $dbFileName .= '.' . $dbExtension;
      }
    }
    if ($overrideFileName) {
      $fileName = $fileBase;
      $extension = pathinfo($fileName, PATHINFO_EXTENSION);
      if (empty($extension) && !empty($dbExtension)) {
        $fileName .= '.' . $dbExtension;
      }
    } elseif (!empty($fileBase)) {
      if (empty($project)) {
        throw new RuntimeException($this->l->t('No project given, unable generate a file-name.'));
      }
      $fileName = $this->projectService->participantFilename($fileBase, $musician);
      if (!empty($dbExtension)) {
        $fileName .= '.' . $dbExtension;
      }
    } elseif (!empty($optionValue)) {
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
    if (!empty($folderPath)) {
      try {
        $filesAppLinkFolder = $this->userStorage->getFilesAppLink($folderPath, subDir: true);
        $filesAppTarget = md5($filesAppLinkFolder);
        $filesAppPath = $folderPath . $subDirPrefix;
        try {
          $filesAppLink = $this->userStorage->getFilesAppLink($filesAppPath, subDir: true);
        } catch (\OCP\Files\NotFoundException $e) {
          $this->logDebug('No file found for ' . $filesAppPath);
          $filesAppLink = $filesAppLinkFolder;
        }
      } catch (\OCP\Files\NotFoundException $e) {
        $this->logDebug('No folder found for ' . $folderPath);
        $filesAppPath = '';
        $filesAppLink = '';
      }
    } else {
      $filesAppPath = '';
      $filesAppLink = '';
      $filesAppTarget = '_blank';
    }
    $placeHolder = empty($fileBase)
      ? $this->l->t('Drop files here or click to upload files.')
      : $this->l->t('Load %s', pathinfo($fileName, PATHINFO_FILENAME));
    $optionValueName = $this->pme->cgiDataName($inputValueName ?? self::participantFieldValueFieldName($fieldId));

    return (new TemplateResponse(
      $this->appName(),
      'fragments/participant-fields/db-file-upload-row', [
        'fieldId' => $fieldId,
        'optionKey' => $optionKey,
        'optionValue' => $optionValue,
        'musicianId' => empty($musician) ? 0 : $musician->getId(),
        'projectId' => empty($project) ? 0 : $project->getId(),
        'fileBase' => $fileBase,
        'fileName' => $fileName,
        'participantFolder' => $folderPath,
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
   * Generate a link to the files-app if appropriate. The link is always a
   * link to a folder, so for FieldMultiplicity::SIMPLE the documents folder is used.
   *
   * @param null|Entities\ProjectParticipantField $field
   *
   * @param Entities\Musician $musician
   *
   * @param null|Entities\Project $project
   *
   * @param null|string $subFolder
   *
   * @return null|array
   */
  private function getFilesAppLink(
    ?Entities\ProjectParticipantField $field,
    Entities\Musician $musician,
    ?Entities\Project $project = null,
    ?string $subFolder = null,
  ):?array {
    $pathChain = [];
    $project = $project ?? $this->project;
    if (!empty($field)) {
      $project = $project ?? $field->getProject();
      $dataType = $field->getDataType();
      switch ($dataType) {
        case FieldType::RECEIVABLES:
        case FieldType::LIABILITIES:
        case FieldType::DB_FILE:
          $pathChain[] = $this->getDocumentsFolderName();
          if ($dataType != FieldType::DB_FILE) {
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
        $this->logException($e, level: \OCP\ILogger::DEBUG);
        array_pop($pathChain);
      }
    }
    return [ $filesAppLink, $filesAppTarget ];
  }

  /**
   * @param null|Entities\ProjectParticipantField $field
   *
   * @param Entities\Musician $musician
   *
   * @param null|Entities\Project $project
   *
   * @param null|string $subFolder
   *
   * @param null|string $toolTip
   *
   * @return string HTML fragment.
   */
  private function getFilesAppAnchor(
    ?Entities\ProjectParticipantField $field,
    Entities\Musician $musician,
    ?Entities\Project $project = null,
    ?string $subFolder = null,
    ?string $toolTip = null,
  ):string {
    if (!empty($toolTip)) {
      $toolTip = $this->toolTipsService['participant-attachment-open-parent'] . '<br/>' . $toolTip;
    } else {
      $toolTip = $this->toolTipsService['participant-attachment-open-parent'];
    }
    try {
      list($filesAppLink, $filesAppTarget) = $this->getFilesAppLink($field, $musician, $project, $subFolder);
      $html = '<a href="' . $filesAppLink . '" target="'.$filesAppTarget.'"
       title="' . $toolTip . '"
       class="button operation open-parent tooltip-auto'.(empty($filesAppLink) ? ' disabled' : '').'"
       ></a>';
    } catch (\OCP\Files\NotFoundException $e) {
      $html = '';
    }
    return $html;
  }
}
