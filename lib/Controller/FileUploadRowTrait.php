<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\AppFramework\Http\Response;

use OCA\CAFEVDB\Storage\Database\Storage as DatabaseStorage;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Common\Util;

/** Handle PME file-uplaod row. The controller must make sure to define all ingredients. */
trait FileUploadRowTrait
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /**
   * @param string $files File-uploads presented by PHP, JSON encoded.
   *
   * @param string $optionKey Optional index into $files.
   *
   * @param bool $multiple Whether multiple uploads are to be expected.
   *
   * @return array|Response
   *
   * @SuppressWarnings(PHPMD.Superglobals)
   */
  protected function prepareUploadInfo(?string $files, string $optionKey, bool $multiple)
  {
    $uploadMaxFilesize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
    $postMaxSize = \OCP\Util::computerFileSize(ini_get('post_max_size'));
    $maxUploadFileSize = min($uploadMaxFilesize, $postMaxSize);
    $maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);

    $fileKey = 'files';

    if (!empty($files)) {

      // files come from upload/stash
      $files = json_decode($files, true);
      if (!$multiple && count($files) == 1) {
        $files[$optionKey] = array_shift($files);
      }

    } else {

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

      $this->logDebug('PARAMETERS '.print_r($this->parameterService->getParams(), true));

      $files = Util::transposeArray($_FILES[$fileKey]);
      if (is_array($files[$optionKey]['name'])) {
        $files = Util::transposeArray($files[$optionKey]);
      }

    }

    if (!$multiple) {
      if (count($files) !== 1) {
        return self::grumble($this->l->t('Only single file uploads are supported here, number of submitted uploads is %d.', count($files)));
      }
      if (empty($files[$optionKey])) {
        return self::grumble($this->l->t('Invalid file index, expected the key "%s", got "%s".', [ $optionKey, array_keys($files)[0] ]));
      }
    }

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
      if (!empty($file['original_name'])) {
        $file['name'] = $file['original_name'];
      } else {
          $file['original_name'] = $file['name']; // clone
      }

      $file['str_error'] = Util::fileUploadError($file['error'], $this->l);
      if ($file['error'] != UPLOAD_ERR_OK) {
        $this->logInfo('Upload error ' . print_r($file, true));
        continue;
      }
    }

    return $files;
  }

  /**
   * @param array $file File-upload data as prepared by self::prepareUploadInfo().
   *
   * @return string
   */
  protected function getUploadContent(array $file):string
  {
    if (!file_exists($file['tmp_name'])) {
      /** @var UserStorage $appStorage */
      $appStorage = $this->di(AppStorage::class);
      $appFile = $appStorage->getFile(AppStorage::UPLOAD_FOLDER, $file['tmp_name']);
      $fileData = $appFile->getContent();
    } else {
      $fileData = file_get_contents($file['tmp_name']);
    }
    return $fileData;
  }

  /**
   * @param array $file File-upload data as prepared by self::prepareUploadInfo().
   */
  protected function removeStashedFile(array $file):void
  {
    if (!file_exists($file['tmp_name'])) {
      /** @var UserStorage $appStorage */
      $appStorage = $this->di(AppStorage::class);
      $appFile = $appStorage->getFile(AppStorage::UPLOAD_FOLDER, $file['tmp_name']);
      $appFile->delete();
    } else {
      unlink($file['tmp_name']);
    }
  }

  /**
   * Return the underline storage entity if the upload refers to db-backed
   * cloud-file.
   *
   * @param array $file File-upload data as prepared by
   * FileUploadRowTrait::prepareUploadInfo().
   *
   * @return null|Entities\EncryptedFile
   */
  protected function getDatabaseFile(array $file):?Entities\EncryptedFile
  {
    if (!isset($file['cloud_path'])) {
      return null;
    }
    $cloudPath = $file['cloud_path'];

    /** @var UserStorage $userStorage */
    $userStorage = $this->di(UserStorage::class);

    return $userStorage->getDatabaseFile($cloudPath);
  }
}
