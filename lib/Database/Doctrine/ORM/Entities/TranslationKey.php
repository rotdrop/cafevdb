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
 * Translationkey
 *
 * @ORM\Table(name="TranslationKeys",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(name="phrase", columns={"phrase"})
 *   })
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\TranslationKeysRepository")
 */
class TranslationKey implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var int
   *
   * @ORM\Column(name="id", type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var string
   *
   * @ORM\Column(name="phrase", type="string", length=768, nullable=false,
   *   options={
   *     "comment":"Keyword to be translated. Normally en_US, but could be any unique tag"
   *   })
   */
  private $phrase;

  /**
   * @ORM\OneToMany(targetEntity="Translation", mappedBy="translationKey", cascade={"all"})
   */
  private $translations;

  /**
   * @ORM\OneToMany(targetEntity="TranslationLocation", mappedBy="translationKey", cascade={"all"})
   */
  private $locations;

  public function __construct() {
    $this->arrayCTOR();
    $this->translations = new ArrayCollection();
    $this->locations = new ArrayCollection();
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
   * Set phrase.
   *
   * @param string $phrase
   *
   * @return Phrase
   */
  public function setPhrase($phrase)
  {
    $this->phrase = $phrase;

    return $this;
  }

  /**
   * Get phrase.
   *
   * @return string
   */
  public function getPhrase()
  {
    return $this->phrase;
  }

  /**
   * Get linked Translation entities.
   *
   * @return ArrayCollection[]
   */
  public function getTranslations()
  {
    return $this->translations;
  }

  /**
   * Get linked TranslationLocation entities.
   *
   * @return ArrayCollection[]
   */
  public function getLocations()
  {
    return $this->locations;
  }
}
