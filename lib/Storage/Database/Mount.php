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

/**
 * Mount parts of the database-storage somewhere.
 *
 * @todo This is just a dummy for now in order to test the integration
 * with the surrounding cloud.
 */
class Mount implements IMountProvider
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public function __construct(ConfigService $configService)
  {
    $this->configService = $configService;
    $this->l = $this->l10n();
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
    if (!$this->inGroup($user->getUID())) {
      return [];
    }

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

    return $mounts;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
