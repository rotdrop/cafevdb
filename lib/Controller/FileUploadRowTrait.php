<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Controller;

use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Common\Util;

/** Handle PME file-uplaod row. The controller must make sure to define all ingredients. */
trait FileUploadRowTrait
{
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  protected function prepareUploadInfo($files, $optionKey, bool $multiple)
  {
    $upload_max_filesize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
    $post_max_size = \OCP\Util::computerFileSize(ini_get('post_max_size'));
    $maxUploadFileSize = min($upload_max_filesize, $post_max_size);
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
    foreach ($files as $index => &$file) {

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

  protected function getUploadContent($file)
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

  protected function removeStashedFile($file)
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
}
