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

use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\ProjectService;

use OCA\CAFEVDB\Storage\UserStorage;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;

/** File related per-participant-fields. */
trait ParticipantFileFieldsTrait
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var ProjectService */
  protected $projectService;

  /** @var UserStorage */
  protected $userStorage;

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var PHPMyEdit */
  protected $pme;

  /** Generate one HTML input row for a cloud-file field. */
  protected function cloudFileUploadRowHtml($value, $fieldId, $optionKey, $policy, $subDir, $fileBase, $musician)
  {
    $participantFolder = $this->projectService->ensureParticipantFolder($this->project, $musician);
    // make sure $subDir exists
    if (!empty($subDir)) {
      $subDirPrefix = UserStorage::PATH_SEP . $subDir;
      $this->userStorage->ensureFolder($participantFolder . $subDirPrefix);
    } else {
      $subDirPrefix = '';
    }
    if (!empty($fileBase)) {
      $fileName = $this->projectService->participantFilename($fileBase, $this->project, $musician);
      if (!empty($value)) {
        $fileName .= '.' . pathinfo($value, PATHINFO_EXTENSION);
      }
      $placeHolder = $this->l->t('Load %s', $fileName);
    } else {
      $fileName = $value;
      $placeHolder = $this->l->t('Drop files here or click to upload fileds.');
    }
    if (!empty($value)) {
      $filePath = $participantFolder . $subDirPrefix . UserStorage::PATH_SEP . $fileName;
      try {
        $downloadLink = $this->userStorage->getDownloadLink($filePath);
        $filesAppLink = $this->userStorage->getFilesAppLink($filePath);
      } catch (\OCP\Files\NotFoundException $e) {
        $downloadLink = '#';
        $filesAppLink = '#';
        $value = '<span class="error tooltip-auto" title="' . $filePath . '">' . $this->l->t('The file "%s" could not be found on the server.', $fileName) . '</span>';
      }
    } else {
      $downloadLink = '';
      $filesAppLink = $this->userStorage->getFilesAppLink($participantFolder . $subDirPrefix, true);
    }
    $optionValueName = $this->pme->cgiDataName(self::participantFieldValueFieldName($fieldId))
                     . ($subDir ? '[]' : '');

    return (new TemplateResponse(
      $this->appName(),
      'fragments/participant-fields/cloud-file-upload-row', [
        'fieldId' => $fieldId,
        'optionKey' => $optionKey,
        'optionValue' => $optionValue,
        'subDir' => $subDir,
        'fileBase' => $fileBase,
        'fileName' => $fileName,
        'uploadPolicy' => $policy,
        'participantFolder' => $participantFolder,
        'filesAppLink' => $filesAppLink,
        'downloadLink' => $downloadLink,
        'optionValueName' => $optionValueName,
        'uploadPlaceHolder' => $placeHolder,
        'toolTips' => $this->toolTipsService,
        'toolTipsPrefix' => 'participant-fields',
      ],
      'blank'
    ))->render();
  }

  protected function dbFileUploadRowHtml($optionValue, int $fieldId, string $optionKey, ?string $subDir, ?string $fileBase, Entities\Musician $musician, ?Entities\Project $project = null, bool $overrideFileName = false)
  {
    $project = $project??$this->project;
    $participantFolder = $this->projectService->ensureParticipantFolder($project, $musician, dry: true);
    $subDirPrefix = UserStorage::PATH_SEP . $this->getDocumentsFolderName();
    if (!empty($subDir)) {
      $subDirPrefix .= UserStorage::PATH_SEP . $subDir;
    }
    if (!empty($optionValue)) {
      /** @var Entities\File $file */
      $file = $this->getDatabaseRepository(Entities\File::class)->find($optionValue);
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
      $fileName = $this->projectService->participantFilename($fileBase, $project, $musician);
      if (!empty($dbExtension)) {
        $fileName .= '.' . $dbExtension;
      }
    } else if (!empty($optionValue)) {
      $fileName = $dbFileName;
    } else {
      $fileName = null;
    }
    if (!empty($optionValue)) {
      $requestData = [
        'requesttoken' => \OCP\Util::callRegister(),
      ];
      if (!empty($fileName)) {
        $requestData['fileName'] = $fileName;
      }
      $downloadLink = $this->urlGenerator()->linkToRoute($this->appName().'.downloads.get', [
          'section' => 'database',
          'object' => $optionValue,
      ])
        . '?' . http_build_query($requestData);
    } else {
      $downloadLink = $dbFileName = $dbExtension = '';
    }
    try {
      $filesAppLink = $this->userStorage->getFilesAppLink($participantFolder . $subDirPrefix, true);
    } catch (\OCP\Files\NotFoundException $e) {
      $this->logInfo('No file found for ' . $participantFolder . $subDirPrefix);
      $filesAppLink = '';
    }
    $placeHolder = empty($fileName)
      ? $this->l->t('Drop files here or click to upload fileds.')
      : $this->l->t('Load %s', $fileName);
    $optionValueName = $this->pme->cgiDataName(self::participantFieldValueFieldName($fieldId));

    return (new TemplateResponse(
      $this->appName(),
      'fragments/participant-fields/db-file-upload-row', [
        'fieldId' => $fieldId,
        'optionKey' => $optionKey,
        'optionValue' => $optionValue,
        'fileBase' => $fileBase,
        'fileName' => $fileName,
        'participantFolder' => $participantFolder,
        'filesAppLink' => $filesAppLink,
        'downloadLink' => $downloadLink,
        'optionValueName' => $optionValueName,
        'uploadPlaceHolder' => $placeHolder,
        'toolTips' => $this->toolTipsService,
        'toolTipsPrefix' => 'participant-fields',
      ],
      'blank'
    ))->render();
  }

  /**
   * Generate a link to the files-app if appropriate
   */
  private function getFilesAppLink(Entities\ProjectParticipantField $field, Entities\Musician $musician)
  {
    $pathChain = [];
    switch ($field->getDataType()) {
      case FieldType::SERVICE_FEE:
      case FieldType::DB_FILE:
        $pathChain[] = $this->getDocumentsFolderName();
        if ($field->getMultiplicity() != FieldMultiplicity::SIMPLE) {
          $pathChain[] = $field->getName();
        }
        break;
      case FieldType::CLOUD_FILE:
        if ($field->getMultiplicity() != FieldMultiplicity::SIMPLE) {
          $pathChain[] = $field->getUntranslatedName();
        }
        break;
      case FieldType::CLOUD_FOLDER:
        $pathChain[] = $field->getUntranslatedName();
        break;
      default:
        return null;
    }
    $participantFolder = $this->projectService->ensureParticipantFolder($this->project, $musician, dry: true);
    array_unshift($pathChain, $participantFolder);
    $folderPath = implode(UserStorage::PATH_SEP, $pathChain);
    try {
      $filesAppTarget = md5($this->userStorage->getFilesAppLink($participantFolder));
      $filesAppLink = $this->userStorage->getFilesAppLink($folderPath, true);
    } catch (/*\OCP\Files\NotFoundException*/ \Throwable $e) {
      $this->logException($e);
      $filesAppLink = '';
    }
    return [ $filesAppLink, $filesAppTarget ];
  }

  private function getFilesAppAnchor(Entities\ProjectParticipantField $field, Entities\Musician $musician)
  {
    list($filesAppLink, $filesAppTarget) = $this->getFilesAppLink($field, $musician);
    $html = '<a href="' . $filesAppLink . '" target="'.$filesAppTarget.'"
       title="'.$this->toolTipsService['participant-attachment-open-parent'].'"
       class="button operation open-parent tooltip-auto'.(empty($filesAppLink) ? ' disabled' : '').'"
       ></a>';
    return $html;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
