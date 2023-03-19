<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Loggable;

/**
 * OCA\CAFEVDB\Database\Doctrine\ORM\Entities\LogEntity
 *
 * @ORM\Table(
 *     name="ExtLogEntries",
 *     options={"row_format":"DYNAMIC"},
 *  indexes={
 *      @ORM\Index(name="log_class_lookup_idx", columns={"object_class"}),
 *      @ORM\Index(name="log_date_lookup_idx", columns={"logged_at"}),
 *      @ORM\Index(name="log_user_lookup_idx", columns={"username"}),
 *      @ORM\Index(name="log_version_lookup_idx", columns={"object_id", "object_class", "version"}),
 *      @ORM\Index(name="log_action_lookup_idx", columns={"action", "object_class"}),
 *      @ORM\Index(name="log_action_class_lookup_idx", columns={"action"})
 *  }
 * )
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\LogEntriesRepository")
 */
class LogEntry extends Loggable\Entity\MappedSuperclass\AbstractLogEntry
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /*
   * All required columns are mapped through inherited superclass
   */

  /**
   * @var string
   *
   * May key size for MariaDB with decent settings is 3072 / 4 = 768 bytes.
   *
   * We have object_id = X, object_class = 191 (why?), version = 4.
   *
   * So 768 - 4(version) - 191(object_class) = 573 should do.
   *
   * @ORM\Column(name="object_id", length=573, nullable=true)
   */
  protected $objectId;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=45, nullable=true)
   */
  private $remoteAddress;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
  }
  // phpcs:enable

  /**
   * Set remoteAddress.
   *
   * @param null|string $remoteAddress
   *
   * @return Musician
   */
  public function setRemoteAddress($remoteAddress = null)
  {
    $this->remoteAddress = $remoteAddress;

    return $this;
  }

  /**
   * Get remoteAddress.
   *
   * @return Image|null
   */
  public function getRemoteAddress()
  {
    return $this->remoteAddress;
  }
}
