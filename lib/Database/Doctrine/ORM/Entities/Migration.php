<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024 Claus-Justus Heine
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
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @ORM\HasLifecycleCallbacks
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

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=512)
   */
  private $migrationClassName;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
  }
  // phpcs:enable

  /**
   * Set version.
   *
   * @param null|string $version
   *
   * @return Migration
   */
  public function setVersion(?string $version):Migration
  {
    $this->version = $version;

    return $this;
  }

  /**
   * Get version.
   *
   * @return string
   */
  public function getVersion():?string
  {
    return $this->version;
  }

  /**
   * Set the migration class name.
   *
   * @param string $className
   *
   * @return Migration
   */
  public function setMigrationClassName(string $className):Migration
  {
    $this->migrationClassName = $className;

    return $this;
  }

  /**
   * Get migrationClassName.
   *
   * @return string
   */
  public function getMigrationClassName():?string
  {
    return $this->migrationClassName;
  }
}
