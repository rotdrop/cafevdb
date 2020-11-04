<?php

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
