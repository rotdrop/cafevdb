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
 * Translations
 *
 * @ORM\Table(name="Translations",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(name="keyId_locale", columns={"key_id", "locale"})
 *   })
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\TranslationsRepository")
 */
class Translation implements \ArrayAccess
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
   * @var int
   *
   * @ORM\Column(name="key_id", type="integer", nullable=false)
   */
  private $keyId;

  /**
   * @var string
   *
   * @ORM\Column(name="locale", type="string", length=5, nullable=false, options={
   *   "fixed":true,
   *   "comment":"Locale for translation, .e.g. en_US"
   * })
   */
  private $locale;

  /**
   * @var string
   *
   * @ORM\Column(name="translation", type="string", length=1024, nullable=false)
   */
  private $translation;

  /**
   * @ORM\ManyToOne(targetEntity="TranslationKey", inversedBy="translations")
   * @ORM\JoinColumn(name="key_id", referencedColumnName="id", onDelete="CASCADE")
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
  public function getId()
  {
    return $this->id;
  }

  /**
   * Set key.
   *
   * @param string $key
   *
   * @return Key
   */
  public function setKeyId($key)
  {
    $this->key = $key;

    return $this;
  }

  /**
   * Get key.
   *
   * @return string
   */
  public function getKeyId()
  {
    return $this->key;
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
   * Set locale.
   *
   * @param string $locale
   *
   * @return Translation
   */
  public function setLocale($locale)
  {
    $this->locale = $locale;

    return $this;
  }

  /**
   * Get locale.
   *
   * @return string
   */
  public function getLocale()
  {
    return $this->locale;
  }

  /**
   * Set translation.
   *
   * @param string $translation
   *
   * @return Translation
   */
  public function setTranslation($translation)
  {
    $this->translation = $translation;

    return $this;
  }

  /**
   * Get translation.
   *
   * @return string
   */
  public function getTranslation()
  {
    return $this->translation;
  }

}
