<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * Projects
 *
 * @ORM\Table(name="Migrations")
 * @ORM\Entity
 */
class Migration implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;

  /**
   * @var string
   *
   * Unique sortable migration string in the format YYYYMMDDHHMMSS
   *
   * @ORM\Column(type="string", length=14, options={"fixed"=true, "collation"="ascii_general_ci"})
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   */
  private $version;

  public function __construct() {
    $this->arrayCTOR();
  }

  /**
   * Set version.
   *
   * @param string $version
   *
   * @return Project
   */
  public function setVersion($version)
  {
    $this->version = $version;

    return $this;
  }

  /**
   * Get version.
   *
   * @return string
   */
  public function getVersion()
  {
    return $this->version;
  }
}
