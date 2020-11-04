<?php

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
 *     @ORM\UniqueConstraint(name="key_file_line", columns={"key_id", "file", "line"})
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
   * @ORM\Column(name="file", type="string", length=766, nullable=false)
   */
  private $file;

  /**
   * @var int
   *
   * @ORM\Column(name="line", type="integer", length=11, nullable=false)
   */
  private $line;

  /**
   * @ORM\ManyToOne(targetEntity="TranslationKey", inversedBy="locations")
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
