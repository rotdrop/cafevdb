<?php
/**
 * Orchestra member, musician and project management application.
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
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\Storage\DatabaseStorageUtil;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Common\Util;

class DownloadsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public const SECTION_DATABASE = 'database';
  public const SECTION_FILECACHE = 'filecache';

  public const OBJECT_COLLECTION = 'collection';
  public const COLLECTION_ITEMS = 'items';

  public function __construct(
    $appName
    , IRequest $request
    , ConfigService $configService
    , EntityManager $entityManager
  ) {

    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->l = $this->l10N();
  }

  /**
   * Fetch something and return it as download.
   *
   * @param string $section Origin of the download. Currently only
   * self::SECTION_DATABASE and self::SECTION_FILECACHE for data-base
   * Entities\File objects and cache-objects of the per-user file-cache
   *
   * @param string $object Something identifying the object in the
   * context of $section. If $object == self::OBJECT_COLLECTION the
   * actual items of the collection are expected as query parameters
   * and a zip-archive of those items will be presented as download,
   * optionally wrapped into the sub-directory name given by
   * $collectionName.
   *
   * @param array $items Collection items if $object equals
   * self::OBJECT_COLLECTION
   *
   * @param null|string $fileName Optional filename for the download
   *
   * @return mixed \OCP\Response Something derived from \OCP\Response
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
          $file = $this->getDatabaseRepository(Entities\File::class)->find($fileId);
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
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

  /**
   * Fetch something and return it as download.
   *
   * @param string $section Cosmetics, for grouping purposes
   *
   * @param string $object Something identifying the object in the
   * context of $section.
   *
   * @param array $items Collection items if $section equals
   * self::OBJECT_COLLECTION
   *
   * @param null|string $fileName Optional filename for the download
   *
   * @return mixed \OCP\Response Something derived from \OCP\Response
   *
   * @NoAdminRequired
   */
  public function get(string $section, string $object, array $items = [], ?string $fileName = null)
  {
    return $this->fetch($section, $object, $items, $fileName);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
