<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ImagesService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;

class MoveProjectPosters implements IMigration
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  private const POSTERS_JOIN_TABLE = 'ProjectPosters';

  /** @var IL10N */
  private $l;

  /** @var ProjectService */
  private $projectService;

  /** @var ImagesService */
  private $imagesService;

  public function __construct(
    ILogger $logger
    , IL10N $l10n
    , ProjectService $projectService
    , ImagesService $imagesService
    , EntityManager $entityManager
  ) {
    $this->logger = $logger;
    $this->l = $l10n;
    $this->projectService = $projectService;
    $this->imagesService = $imagesService;
    $this->entityManager = $entityManager;
  }

  public function description():string
  {
    return $this->l->t('Move project posters from database to filesystem storage.');
  }

  public function execute():bool
  {
    // try to migrate as much data as possible
    $numFailures = 0;

    // the idea is that the ProjectPosters join table _entity_ is
    // gone, so just use DBAL
    $connection = $this->entityManager->getConnection();

    $projects = $this->projectService->fetchAll();

    /** @var Entities\Project $project */
    foreach($projects as $project) {
      $postersFolder = $this->projectService->ensurePostersFolder($project);

      $sql = 'SELECT image_id FROM ' . self::POSTERS_JOIN_TABLE . ' WHERE owner_id = ?';
      $stmt = $connection->prepare($sql);
      $stmt->bindValue(1, $project->getId());
      $dbImageIds = $stmt->executeQuery()->fetchFirstColumn();
      $this->logDebug('IMAGES: ' . print_r($dbImageIds, true));

      $fsImageIds = $this->imagesService->getImageIds(ImagesService::USER_STORAGE, $postersFolder);
      if (!empty($dbImageIds)) {
        if (!empty($fsImageIds)) {
          $this->logWarn(
            $this->l->t('The project "%1$s" already has the following file-system images: "%2$s".',
                        [ $project->getName(), implode(', ', $fsImageIds)])
          );
        }
      }

      foreach ($dbImageIds as $dbImageId) {
        try {
          /** @var \OCP\Image $image */
          list($image, $fileName) = $this->imagesService->getImage(ImagesService::DATABASE_STORAGE, $project->getId(), $dbImageId);
          if (empty($image)) {
            $this->logWarn(
              $this->l->t('Unable to fetch data-base image "%1$d" for project "%2$s".',
                          [ $dbImageId, $project->getName() ])
            );
            continue;
          }

          // make the filename look a bit better ..
          if (strpos($fileName, ImagesService::DATABASE_STORAGE) === 0) {
            $fileName = $project->getName() . '-' . $dbImageId;
          }
          $ext = pathinfo($fileName, PATHINFO_EXTENSION);
          if (empty($ext)) {
            $mimeType = $image->mimeType();
            $ext = Util::fileExtensionFromMimeType($mimeType);
            if (empty($ext)) {
              $this->logWarn('Unable to determine file-extension for mime-type ' . $mimeType);
            } else {
              $fileName .= '.' . $ext;
            }
          }

          $fileName = $this->imagesService->storeImage($image, ImagesService::USER_STORAGE, $postersFolder, $fileName, $fileName);
          $this->logInfo(sprintf(
            'Stored data-base image id "%1$d" of project "%2$s" in file-system storage with file-name "%3$s".',
            $dbImageId, $project->getName(), $fileName));

          // delete the join table entry with "pure DBAL"
          $sql = 'DELETE FROM ' . self::POSTERS_JOIN_TABLE . ' WHERE image_id = ?';
          $stmt = $connection->prepare($sql);
          $stmt->bindValue(1, $dbImageId);
          $stmt->executeQuery();

          // Images entity is still there, so use ORM
          $this->imagesService->deleteImage(ImagesService::DATABASE_STORAGE, $project->getId(), $dbImageId);
          $this->logInfo(sprintf('Deleted old data-base image with id "%1$d".', $dbImageId));
        } catch (\Throwable $t) {
          $this->logException($t, $this->l->t(
            'Unable to move data-base image id "%1$d" of project "%2$s" to file-system storage with file-name "%3$s".',
            [ $dbImageId, $project->getName(), $fileName ])
          );
          $numFailures++;
          continue;
        }

      }
    }

    if ($numFailures === 0) {
      // remove the join table
      $connection->query('DROP TABLE ' . self::POSTERS_JOIN_TABLE);
    }

    return $numFailures == 0;
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
