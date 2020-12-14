<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;

/**
 * Changelog
 *
 * @ORM\Table(name="ChangeLog")
 * @ORM\Entity
 */
class ChangeLog
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var \DateTime
   *
   * @ORM\Column(type="datetime")
   */
  private $updated;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   */
  private $user;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   */
  private $host;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   */
  private $operation;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   */
  private $tab;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   */
  private $rowkey;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=255, nullable=true)
   */
  private $col;

  /**
   * @var string|null
   *
   * @ORM\Column(type="blob", length=65535, nullable=true)
   */
  private $oldval;

  /**
   * @var string|null
   *
   * @ORM\Column(type="blob", length=65535, nullable=true)
   */
  private $newval;

  public function __construct() {
    $this->arrayCTOR();
  }

  /**
   * Get id.
   *
   * @return int
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * Set updated.
   *
   * @param \DateTime|null $updated
   *
   * @return Changelog
   */
  public function setUpdated($updated = null)
  {
    if (empty($updated)) {
      $updated = new \DateTime;
    }
    $this->updated = $updated;

    return $this;
  }

  /**
   * Get updated.
   *
   * @return \DateTime
   */
  public function getUpdated()
  {
    return $this->updated;
  }

  /**
   * Set user.
   *
   * @param string|null $user
   *
   * @return Changelog
   */
  public function setUser($user = null)
  {
    $this->user = $user;

    return $this;
  }

  /**
   * Get user.
   *
   * @return string|null
   */
  public function getUser()
  {
    return $this->user;
  }

  /**
   * Set host.
   *
   * @param string|null $host
   *
   * @return Changelog
   */
  public function setHost($host = null)
  {
    $this->host = $host;

    return $this;
  }

  /**
   * Get host.
   *
   * @return string|null
   */
  public function getHost()
  {
    return $this->host;
  }

  /**
   * Set operation.
   *
   * @param string|null $operation
   *
   * @return Changelog
   */
  public function setOperation($operation = null)
  {
    $this->operation = $operation;

    return $this;
  }

  /**
   * Get operation.
   *
   * @return string|null
   */
  public function getOperation()
  {
    return $this->operation;
  }

  /**
   * Set tab.
   *
   * @param string|null $tab
   *
   * @return Changelog
   */
  public function setTab($tab = null)
  {
    $this->tab = $tab;

    return $this;
  }

  /**
   * Get tab.
   *
   * @return string|null
   */
  public function getTab()
  {
    return $this->tab;
  }

  /**
   * Set rowkey.
   *
   * @param string|null $rowkey
   *
   * @return Changelog
   */
  public function setRowkey($rowkey = null)
  {
    $this->rowkey = $rowkey;

    return $this;
  }

  /**
   * Get rowkey.
   *
   * @return string|null
   */
  public function getRowkey()
  {
    return $this->rowkey;
  }

  /**
   * Set col.
   *
   * @param string|null $col
   *
   * @return Changelog
   */
  public function setCol($col = null)
  {
    $this->col = $col;

    return $this;
  }

  /**
   * Get col.
   *
   * @return string|null
   */
  public function getCol()
  {
    return $this->col;
  }

  /**
   * Set oldval.
   *
   * @param string|null $oldval
   *
   * @return Changelog
   */
  public function setOldval($oldval = null)
  {
    $this->oldval = $oldval;

    return $this;
  }

  /**
   * Get oldval.
   *
   * @return string|null
   */
  public function getOldval()
  {
    return $this->oldval;
  }

  /**
   * Set newval.
   *
   * @param string|null $newval
   *
   * @return Changelog
   */
  public function setNewval($newval = null)
  {
    $this->newval = $newval;

    return $this;
  }

  /**
   * Get newval.
   *
   * @return string|null
   */
  public function getNewval()
  {
    return $this->newval;
  }
}
