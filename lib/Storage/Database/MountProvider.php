<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Storage\Database;

// FIXME internal
use OC\Files\Mount\MountPoint;

use OCP\Files\Config\IMountProvider;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

/**
 * Mount parts of the database-storage somewhere.
 *
 * @todo This is just a dummy for now in order to test the integration
 * with the surrounding cloud.
 */
class MountProvider implements IMountProvider
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  private static $recursionLevel = 0;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
  ) {
    $this->configService = $configService;
    $this->l = $this->l10n();
    $this->entityManager = $entityManager;
  }

  /**
   * Get all mountpoints applicable for the user
   *
   * @param \OCP\IUser $user
   * @param \OCP\Files\Storage\IStorageFactory $loader
   * @return \OCP\Files\Mount\IMountPoint[]
   */
  public function getMountsForUser(IUser $user, IStorageFactory $loader)
  {
    if (self::$recursionLevel > 0) {
      // the getNode() stuff below triggers recursion.
      $this->logDebug('RECURSION: ' . self::$recursionLevel);
      return [];
    }

    if (!$this->inGroup($user->getUID())) {
      return [];
    }

    if (!$this->entityManager->connected()) {
      $this->logDebug('EntityManager is not connected for user ' . $user->getUID());
      return [];
    }

    self::$recursionLevel++;

    $sharedFolder = $this->getSharedFolderPath();

    /** @var OCA\CAFEVDB\Storage\UserStorage $userStorage */
    $userStorage = $this->di(\OCA\CAFEVDB\Storage\UserStorage::class);
    $node = $userStorage->get($sharedFolder);
    if (empty($node) || $node->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
      $this->logException(new \Exception('NO shared folder for ' . $user->getUID()));
      --self::$recursionLevel;
      return [];
    }
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    try {
      $node = $node->get($projectsFolder);
      if (empty($node) || $node->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
        $this->logException(new \Exception('NO projects folder for ' . $user->getUID()));
        --self::$recursionLevel;
        return [];
      }
    } catch (\Throwable $t) {
      $this->logException(new \Exception('NO projects folder ' . $projectsFolder . ' for ' . $user->getUID()));
      --self::$recursionLevel;
      return [];
    }

    $this->logDebug($user->getUID() . ' ' . $sharedFolder);

    $mounts = [];

    if ($user->getUID() === $this->shareOwnerId()) {

      $storage = new Storage([]);
      \OC\Files\Cache\Storage::getGlobalCache()->loadForStorageIds([ $storage->getId(), ]);

      $mounts[] = new class(
        $storage,
        '/' . $user->getUID()
        . '/files'
        . '/' . $this->getSharedFolderPath()
        . '/' . $this->appName() . '-database',
        null,
        $loader,
        [
          'filesystem_check_changes' => 1,
          'readonly' => true,
          'previews' => true,
          'enable_sharing' => true,
        ]
      ) extends MountPoint { public function getMountType() { return 'database'; } };

    }

    $storage = new BankTransactionsStorage([]);
    \OC\Files\Cache\Storage::getGlobalCache()->loadForStorageIds([ $storage->getId(), ]);

    $mounts[] = new class(
      $storage,
      '/' . $user->getUID()
      . '/files'
      . '/' . $this->getBankTransactionsPath(),
      null,
      $loader,
      [
        'filesystem_check_changes' => 1,
        'readonly' => true,
        'previews' => true,
        'enable_sharing' => true,
      ]
    ) extends MountPoint { public function getMountType() { return 'database'; } };

    $fieldsRepo = $this->getDatabaseRepository(Entities\ProjectParticipantField::class);
    $fields = $fieldsRepo->findBy([ 'dataType' => FieldType::DB_FILE ]);
    // $this->logInfo(count($fields));

    $projectsRepo = $this->getDatabaseRepository(Entities\Project::class);
    $projects = $projectsRepo->findBy([
      'participantFields.dataType' => [ FieldType::DB_FILE, FieldType::SERVICE_FEE ],
      'participantFields.multiplicity' => [ FieldMultiplicity::SIMPLE, FieldMultiplicity::RECURRING ],
    ]);

    /** @var ProjectService $projectService */
    $projectService = $this->di(ProjectService::class);

    $fileCriteria = DBUtil::criteriaWhere([
      'dataType' => FieldType::DB_FILE,
      '|multiplicity' => FieldMultiplicity::RECURRING,
    ]);

    /** @var Entities\Project $project */
    foreach ($projects as $project) {
      $fileFields = $project->getParticipantFields()->matching($fileCriteria);
      if (count($fileFields) < 1) {
        continue;
      }

      /** @var Entities\ProjectParticipant $participant */
      foreach ($project->getParticipants() as $participant) {
        $folder = $projectService->ensureParticipantFolder($project, $participant->getMusician(), false);
        $storage = new ProjectParticipantsStorage([
          'participant' => $participant,
        ]);
        \OC\Files\Cache\Storage::getGlobalCache()->loadForStorageIds([ $storage->getId(), ]);

        $mounts[] = new class(
          $storage,
          '/' . $user->getUID()
          . '/files'
          . $folder
          . '/'. $this->l->t('documents'),
          null,
          $loader,
          [
            'filesystem_check_changes' => 1,
            'readonly' => true,
            'previews' => true,
            'enable_sharing' => true,
          ]
        ) extends MountPoint { public function getMountType() { return 'database'; } };

      }
    }

    --self::$recursionLevel;
    return $mounts;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
