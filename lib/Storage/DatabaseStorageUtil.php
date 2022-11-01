<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Storage;

use ZipStream\ZipStream;
use ZipStream\Option\Archive as ArchiveOptions;

use OCP\IL10N;
use OCP\ILogger;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Controller\DownloadsController;

/** Support functions for the database storage backend. */
class DatabaseStorageUtil
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const PATH_SEP = '/';

  /** @var string */
  protected $appName;

  /**
   * @param string $appName Application name.
   *
   * @param EntityManager $entityManager The ...
   *
   * @param ILogger $logger Cloud-logger.
   *
   * @param IL10N $l10n Guess what.
   */
  public function __construct(
    string $appName,
    EntityManager $entityManager,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->appName = $appName;
    $this->entityManager = $entityManager;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * Find a database file by entity, file-id or file-name.
   *
   * @param Entities\DatabaseStorageFile|int $entityOrId Either the entity-id or the entity itself.
   *
   * @return null|Entities\File
   */
  public function get(mixed $entityOrId):?Entities\DatabaseStorageFile
  {
    if ($entityOrId instanceof Entities\DatabaseStorageFile) {
      return $entityOrId;
    }
    try {
      return $this->getDatabaseRepository(Entities\DatabaseStorageFile::class)->find($entityOrId);
    } catch (\Throwable $t) {
      // nothing
    }
    return null;
  }

  /**
   * Get a download-link for either a single database file or a collection of
   * database files.
   *
   * @param int|Entities\DatabaseStorageFile|array $fileIdentifier Either a single
   * object understood by DatabaseStorageUtil::get() or an array of such identifiers.
   *
   * @param null|string $fileName Download-filename.
   *
   * @return string Return a download-link for the requested objects.
   */
  public function getDownloadLink($fileIdentifier, ?string $fileName = null):string
  {
    $urlGenerator = \OC::$server->getURLGenerator();
    $queryParameters = [
      'requesttoken' => \OCP\Util::callRegister(),
      'fileName' => $fileName,
    ];

    if (is_array($fileIdentifier)) {
      $items = [];
      foreach ($fileIdentifier as $identifier) {
        $items[] = $this->get($identifier)->getId();
      }

      $filesUrl = $urlGenerator->linkToRoute(
        $this->appName.'.downloads.get', [
          'section' => DownloadsController::SECTION_DATABASE,
          'object' => DownloadsController::OBJECT_COLLECTION,
        ]);
      $queryParameters['items'] = $items;
    } else {
      $file = $this->get($fileIdentifier);
      $id = $file->getId();

      $filesUrl = $urlGenerator->linkToRoute(
        $this->appName.'.downloads.get', [
          'section' => DownloadsController::SECTION_DATABASE,
          'object' => $id,
        ]);
    }

    $filesUrl .= '?' . http_build_query($queryParameters);

    return $filesUrl;
  }

  /**
   * Pack the collection of Entities\File into a zip-archive.
   *
   * @param array $items Something which can be converted by
   * self::get() into Entities\DatabaseStorageFile. null entries are gracefully
   * filtered away.
   *
   * @param null|string $folderName If not null the zip archive will
   * contain all data in the given $folderName.
   *
   * @return string Binary zip-archive data.
   */
  public function getCollectionArchive(array $items, ?string $folderName = null)
  {
    $folderName = empty($folderName) ? '' : $folderName . self::PATH_SEP;

    $dataStream = fopen("php://memory", 'w');
    $zipStreamOptions = new ArchiveOptions;
    $zipStreamOptions->setOutputStream($dataStream);

    $zipStream = new ZipStream($folderName, $zipStreamOptions);

    foreach ($items as $item) {
      if (empty($item)) {
        continue;
      }
      $file = $this->get($item);
      $zipStream->addFile($folderName . $file->getFileName(), $file->getFileData()->getData());
    }

    $zipStream->finish();
    rewind($dataStream);
    $data = stream_get_contents($dataStream);
    fclose($dataStream);

    return $data;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
