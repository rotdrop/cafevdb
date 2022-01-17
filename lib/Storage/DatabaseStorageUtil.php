<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Storage;

use ZipStream\ZipStream;
use ZipStream\Option\Archive as ArchiveOptions;

use OCP\IL10N;
use OCP\ILogger;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Controller\DownloadsController;

class DatabaseStorageUtil
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const PATH_SEP = '/';

  /** @var string */
  protected $appName;

  public function __construct(
    $appName
    , EntityManager $entityManager
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appName = $appName;
    $this->entityManager = $entityManager;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * Find a database file by entity, file-id or file-name.
   *
   * @param Entities\File|string|int $fileIdentifier
   *
   * @return null|Entities\File
   */
  public function get($fileIdentifier):?Entities\File
  {
    if ($fileIdentifier instanceof Entities\File) {
      return $fileIdentifier;
    }
    try {
      $id = filter_var($fileIdentifier, FILTER_VALIDATE_INT, ['min_range' => 1]);
      if ($id !== false) {
        return $this->getDatabaseRepository(Entities\File::class)->find($id);
      } else if (is_string($fileIdentifier)) {
        return $this->getDatabaseRepository(Entities\File::class)->findOneBy([ 'fileName' => $fileIdentifier ]);
      }
    } catch (\Throwable $t) {
      // nothing
    }
    return null;
  }

  /**
   * Get a download-link for either a single database file or a collection of
   * database files.
   *
   * @param string|int|Entities\File|array $fileIdentifier Either a single
   * object understood by DatabaseStorageUtil::get().
   *
   * @return string Return a download-link for the requested objects.
   *
   */
  public function getDownloadLink($fileIdentifier, $fileName = null):string
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
   * self::get() into Entities\File. null entries are gracefully
   * filtered away.
   *
   * @param null|string $folderName If not null the zip archive will
   * contain all data in the given $folderName.
   *
   * @return string Binary zip-archive data.
   *
   * @todo Support streaming, maybe.
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
