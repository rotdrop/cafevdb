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
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Search\ISearchQuery;
use OCP\Files\StorageNotAvailableException;

/** Fail cache reads instead of presenting an offline mount as an empty folder. */
class UnavailableCache extends FailedCache
{
  /** @return never */
  private function unavailable():never
  {
    throw new StorageNotAvailableException('Database storage requires the user\'s authorization credentials.');
  }

  /** {@inheritdoc} */
  public function get($file):false|ICacheEntry
  {
    $this->unavailable();
  }

  /** {@inheritdoc} */
  public function getFolderContents(string $folder, ?string $mimeTypeFilter = null):array
  {
    $this->unavailable();
  }

  /** {@inheritdoc} */
  public function getFolderContentsById(int $fileId, ?string $mimeTypeFilter = null):array
  {
    $this->unavailable();
  }

  /** {@inheritdoc} */
  public function getId($file):int
  {
    $this->unavailable();
  }

  /** {@inheritdoc} */
  public function getParentId($file):int
  {
    $this->unavailable();
  }

  /** {@inheritdoc} */
  public function search($pattern)
  {
    $this->unavailable();
  }

  /** {@inheritdoc} */
  public function searchByMime($mimetype)
  {
    $this->unavailable();
  }

  /** {@inheritdoc} */
  public function searchQuery(ISearchQuery $query)
  {
    $this->unavailable();
  }

  /** {@inheritdoc} */
  public function getAll()
  {
    $this->unavailable();
  }

  /** {@inheritdoc} */
  public function getIncomplete()
  {
    $this->unavailable();
  }

  /** {@inheritdoc} */
  public function getPathById($id)
  {
    $this->unavailable();
  }
}
