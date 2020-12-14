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
use Doctrine\Common\Collections\ArrayCollection;

use OCP\ILogger;

/**
 * TranslationLocations
 *
 * @ORM\Table(name="TranslationLocations",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"key_id", "file", "line"})
 *   })
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\TranslationLocationsRepository")
 */
class TranslationLocation implements \ArrayAccess
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
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private $keyId;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=766, nullable=false)
   */
  private $file;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", length=11, nullable=false)
   */
  private $line;

  /**
   * @ORM\ManyToOne(targetEntity="TranslationKey", inversedBy="locations")
   * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")
   */
  private $translationKey;

  public function __construct() {
    $this->arrayCTOR();
  }

  /**
   * Get id.
   *
   * @return int
   */
  public
  function getId()
  {
    return $this->id;
  }

  /**
   * Set translation key id.
   *
   * @param int $keyId
   *
   * @return TanslationLocation
   */
  public function setKeyId($keyId)
  {
    $this->keyId = $keyId;

    return $this;
  }

  /**
   * Get linked translation key id.
   *
   * @return int
   */
  public function getKeyId()
  {
    return $this->keyId;
  }

  /**
   * Set translation key entity.
   *
   * @param TranslationKey $translationKey
   *
   * @return TanslationLocation
   */
  public function setTranslationKey($translationKey)
  {
    $this->translationKey = $translationKey;

    return $this;
  }

  /**
   * Get linked translation key entity.
   *
   * @return TranslationKey
   */
  public function getTranslationKey()
  {
    return $this->translationKey;
  }

  /**
   * Set file.
   *
   * @param string $file
   *
   * @return TranslationLocation
   */
  public function setFile($file)
  {
    $this->file = $file;

    return $this;
  }

  /**
   * Get file.
   *
   * @return string
   */
  public function getFile()
  {
    return $this->file;
  }

  /**
   * Set line.
   *
   * @param int $line
   *
   * @return TranslationLocation
   */
  public function setLine($line)
  {
    $this->line = $line;

    return $this;
  }

  /**
   * Get line.
   *
   * @return int
   */
  public function getLine()
  {
    return $this->line;
  }

}
