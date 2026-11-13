<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Storage\Database;

use OC\Files\Cache\FailedCache;
use OC\Files\Cache\Storage as StorageCache;
use OC\Files\Mount\MountPoint;
use OC\Files\Storage\FailedStorage;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Storage\IStorage;
use OCP\Files\StorageNotAvailableException;

/** Preserve an existing mount without exposing its cached filesystem. */
class UnavailableMount extends MountPoint
{
  /**
   * @param string $storageId
   * @param ICachedMountInfo $cachedMount
   */
  public function __construct(string $storageId, ICachedMountInfo $cachedMount)
  {
    $this->storageId = StorageCache::adjustStorageId($storageId);
    $this->numericStorageId = $cachedMount->getStorageId();
    $this->rootId = $cachedMount->getRootId();

    $storage = new class([
      'storageId' => $storageId,
      'exception' => new StorageNotAvailableException('Database storage requires the user\'s authorization credentials.'),
    ]) extends FailedStorage {
      private string $storageId;

      /** {@inheritdoc} */
      public function __construct(array $parameters)
      {
        parent::__construct($parameters);
        $this->storageId = $parameters['storageId'];
      }

      /** {@inheritdoc} */
      public function getId():string
      {
        return $this->storageId;
      }

      /** {@inheritdoc} */
      public function getCache(string $path = '', ?IStorage $storage = null):FailedCache
      {
        return new UnavailableCache();
      }
    };

    parent::__construct(
      $storage,
      $cachedMount->getMountPoint(),
      null,
      null,
      [
        'readonly' => true,
        'previews' => false,
        'enable_sharing' => false,
        'authenticated' => true,
      ],
      $cachedMount->getMountId(),
      $cachedMount->getMountProvider(),
    );

    // Keep the scanner's FailedStorage check effective even when a storage
    // factory would normally add wrappers around mounted storages.
    $this->storage = $storage;
  }

  /** {@inheritdoc} */
  public function getMountType()
  {
    return MountProvider::MOUNT_TYPE;
  }

  /** {@inheritdoc} */
  public function setMountPoint($mountPoint)
  {
    throw new StorageNotAvailableException('Unavailable database mounts cannot be moved.');
  }
}
