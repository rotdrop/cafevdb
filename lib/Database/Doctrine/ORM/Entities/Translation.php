<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

use OCP\ILogger;

/**
 * Translations
 *
 * @ORM\Table(name="Translations")
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
