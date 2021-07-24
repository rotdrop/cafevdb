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

namespace OCA\CAFEVDB\Maintenance;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ImagesService;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

class MoveProjectPosters implements IMaintenance
{
  use OCA\CAFEVDB\Traits\LoggerTrait;

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
  ) {
    $this->logger = $logger;
    $this->l = $l10n;
    $this->projectService = $projectService;
    $this->imagesService = $imagesService;
  }

  public function execute()
  {
    // get all projects
    $projects = $this->projectService->fetchAll();
    /** @var Entities\Project $project */
    foreach($projects as $project) {
      $postersFolder = $this->projectService->ensurePostersFolder($project);
      try {
        $dbImageIds = $this->imagesService->getImageIds(Entities\ProjectPoster::class, $project->getId());
      } catch (\Throwable $t) {
        $this->logException($t, $this->l->t('Unable to fetch data-base images, assuming they do not exist.'));
        $dbImageIds = [];
      }
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
          list($image, $fileName) = $this->imagesService->getImage(Entities\ProjectPoster::class, $project->getId(), $dbImageId);
          if (empty($image)) {
            $this->logWarn(
              $this->l->t('Unable to fetch data-base image "%1$d" for project "%2$s".',
                          [ $dbImageId, $project->getName() ])
            );
            continue;
          }
          $fileName = $this->imagesService->storeImage($image, ImagesService::USER_STORAGE, $postersFolder, $fileName, $fileName);
          $this->logInfo($this->l->t(
            'Stored data-base image id "%1$d" of project "%2$s" in file-system storage with file-name "%3$s".',
            [ $dbImageId, $project->getName(), $fileName ]));
          $this->imagesService->deleteImage(Entities\ProjectPoster::class, $project->getId(), $dbImageId);
          $this->logInfo($this->l->t('Deleted old data-base image with id "%1$sd".', $dbImageId));
        } catch (\Throwable $t) {
          $this->logException($t, $this->l->t(
            'Unable to move data-base image id "%1$d" of project "%2$s" to file-system storage with file-name "%3$s".',
            [ $dbImageId, $project->getName(), $fileName ])
          );
          continue;
        }
      }
    }
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
