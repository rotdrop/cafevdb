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
 * MissingTranslations
 *
 * Table to store source-code locations where the phrases stored in
 * the TranslationKey entities are found.
 *
 * @ORM\Table(name="MissingTranslations")
 * @ORM\Entity
 * @Gedmo\Loggable(enabled=false)
 */
class MissingTranslation implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @ORM\ManyToOne(targetEntity="TranslationKey")
   * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")
   * @ORM\Id
   */
  private $translationKey;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=5, nullable=false)
   * @ORM\Id
   */
  private $locale;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
  }
  // phpcs:enable

  /**
   * Set translation key entity.
   *
   * @param null|TranslationKey $translationKey
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
   * @param null|string $locale
   *
   * @return TranslationLocation
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
}
