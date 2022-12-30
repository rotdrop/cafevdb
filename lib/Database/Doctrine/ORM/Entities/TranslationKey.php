<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCP\ILogger;

/**
 * TranslationKey
 *
 * Table to store the original phrase of source-code translations.
 *
 * @ORM\Table(
 *   name="TranslationKeys",
 *   uniqueConstraints={@ORM\UniqueConstraint(columns={"phrase_hash"})}
 * )
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\TranslationKeysRepository")
 * @Gedmo\Loggable(enabled=false)
 */
class TranslationKey implements \ArrayAccess
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
   * @var string
   *
   * @ORM\Column(type="text", nullable=false,
   *   options={
   *     "comment":"Keyword to be translated. Normally the untranslated text in locale en_US, but could be any unique tag"
   *   })
   */
  private $phrase;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=32, nullable=true, options={"fixed"=true})
   * @Gedmo\Slug(fields={"phrase"}, updatable=true, unique=true, handlers={
   *   @Gedmo\SlugHandler(class="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\HashHandler"),
   * })
   */
  private $phraseHash;

  /**
   * @ORM\OneToMany(targetEntity="Translation", mappedBy="translationKey", cascade={"all"}, fetch="EXTRA_LAZY")
   */
  private $translations;

  /**
   * @ORM\OneToMany(targetEntity="TranslationLocation", mappedBy="translationKey", cascade={"all"})
   */
  private $locations;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
    $this->translations = new ArrayCollection();
    $this->locations = new ArrayCollection();
  }
  // phpcs:enable

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
   * @param null|string $phrase
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
   * Set phraseHash.
   *
   * @param null|string $phraseHash
   *
   * @return PhraseHash
   */
  public function setPhraseHash($phraseHash)
  {
    $this->phraseHash = $phraseHash;

    return $this;
  }

  /**
   * Get phraseHash.
   *
   * @return string
   */
  public function getPhraseHash()
  {
    return $this->phraseHash;
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
