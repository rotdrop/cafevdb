<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2024 Claus-Justus Heine
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\Storage\DatabaseStorageUtil;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Common\Util;

/**
 * AJAX end-points for download requests independent from the cloud
 * file-system.
 */
class DownloadsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public const SECTION_DATABASE = 'database';
  public const SECTION_FILECACHE = 'filecache';

  public const OBJECT_COLLECTION = 'collection';
  public const COLLECTION_ITEMS = 'items';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    $appName,
    IRequest $request,
    protected ConfigService $configService,
    protected EntityManager $entityManager,
  ) {

    parent::__construct($appName, $request);

    $this->l = $this->l10N();
  }
  // phpcs:enable

  /**
   * Fetch something and return it as download.
   *
   * @param string $section Origin of the download. Currently only
   * self::SECTION_DATABASE and self::SECTION_FILECACHE for data-base
   * Entities\File objects and cache-objects of the per-user file-cache.
   *
   * @param string $object Something identifying the object in the
   * context of $section. If $object == self::OBJECT_COLLECTION the
   * actual items of the collection are expected as query parameters
   * and a zip-archive of those items will be presented as download,
   * optionally wrapped into the sub-directory name given by
   * $collectionName.
   *
   * @param array $items Collection items if $object equals
   * self::OBJECT_COLLECTION.
   *
   * @param null|string $fileName Optional filename for the download.
   *
   * @return mixed \OCP\Response Something derived from \OCP\Response.
   *
   * @NoAdminRequired
   */
  public function fetch(string $section, string $object, array $items = [], ?string $fileName = null)
  {
    switch ($section) {
      case self::SECTION_DATABASE:
        if ($object == self::OBJECT_COLLECTION) {
          $this->logInfo('ITEMS ' . print_r($items, true));

          if (empty($fileName)) {
            $fileName = $this->timeStamp() . '-' . $this->appName() . '-' . 'download' . '.zip';
          }
          $fileName = basename($fileName, '.zip');

          $fileData = $this->di(DatabaseStorageUtil::class)->getCollectionArchive($items, $fileName);

          return $this->dataDownloadResponse($fileData, $fileName . '.zip', 'application/zip');
        } else {
          $fileId = $object;
          /** @var Entities\File $file */
          $file = $this->getDatabaseRepository(Entities\DatabaseStorageFile::class)->find($fileId);
          if (empty($file)) {
            return self::grumble($this->l->t('File width id %d not found in database-storage.', $fileId));
          }
          $mimeType = $file->getMimeType();
          if (empty($fileName)) {
            $fileName = $file->getFileName();
            if (empty($fileName)) {
              $fileName = $this->appName() . '-' . 'download' . $fileId;
            }
          }
          return $this->dataDownloadResponse($file->getFileData()->getData(), $fileName, $mimeType);
        }
      case self::SECTION_FILECACHE:
        $cacheKey = $object;
        /** @var OCP\ICache $fileCache */
        $fileCache = $this->di(\OCP\ICache::class);
        $fileData = $fileCache->get($cacheKey);
        if (empty($fileData)) {
          return self::grumble($this->l->t('File with cache-key "%s" not found in user\'s file-cache.', $cacheKey));
        }
        $meta = $fileCache->get($cacheKey . '-meta');
        if (!empty($meta)) {
          $fileName = $meta['name']??null;
          $mimeType = $meta['mimeType']??null;
        }
        if (empty($mimeType)) {
          /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
          $mimeTypeDetector = $this->di(\OCP\Files\IMimeTypeDetector::class);
          $mimeType = $mimeTypeDetector->detectString($fileData);
        }
        if (empty($fileName)) {
          $fileName = implode('-', [
            $this->appName(),
            $this->userId(),
            $cacheKey,
          ]) . '.' . Util::fileExtensionFromMimeType($mimeType);
        }
        return $this->dataDownloadResponse($fileData, $fileName, $mimeType);
      default:
        break;
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

  /**
   * Fetch something and return it as download.
   *
   * @param string $section Cosmetics, for grouping purposes.
   *
   * @param string $object Something identifying the object in the
   * context of $section.
   *
   * @param array $items Collection items if $section equals
   * self::OBJECT_COLLECTION.
   *
   * @param null|string $fileName Optional filename for the download.
   *
   * @return mixed \OCP\Response Something derived from \OCP\Response.
   *
   * @NoAdminRequired
   */
  public function get(string $section, string $object, array $items = [], ?string $fileName = null)
  {
    return $this->fetch($section, $object, $items, $fileName);
  }
}
