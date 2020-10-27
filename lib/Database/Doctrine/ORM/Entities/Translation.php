<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

use OCP\ILogger;

/**
 * Instrumente
 *
 * @ORM\Table(name="Translations")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\TranslationssRepository")
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
   * @var string
   *
   * @ORM\Column(name="key", type="string", length=1024, nullable=false, options={
   *   "comment":"Keyword to be translated. Normally en_US, but could be any unique tag"
   * })
   */
  private $key;

  /**
   * @var string
   *
   * @ORM\Column(name="target", type="string", length=5, nullable=false, options={
   *   "fixed":true,
   *   "comment":"Locale for translation, .e.g. en_US"
   * })
   */
  private $target;

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
  public function setKey($key)
  {
    $this->key = $key;

    return $this;
  }

  /**
   * Get key.
   *
   * @return string
   */
  public function getKey()
  {
    return $this->key;
  }

  /**
   * Set target language.
   *
   * @param string $target
   *
   * @return Translation
   */
  public function setTarget($target)
  {
    $this->target = $target;

    return $this;
  }

  /**
   * Get target.
   *
   * @return string
   */
  public function getTarget()
  {
    return $this->target;
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
