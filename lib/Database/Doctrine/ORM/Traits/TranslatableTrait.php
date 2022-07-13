<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

trait TranslatableTrait
{
  /**
   * @Gedmo\Locale(initialize=true)
   * Used locale to override Translation listener`s locale
   * this is not a mapped field of entity metadata, just a simple property
   */
  private $locale;

  /**
   * Set the "local" per-entity override locale for table field translations.
   */
  public function setLocale(?string $locale):self
  {
    $this->locale = $locale;
    return $this;
  }

  /**
   * Get the "locale" per-entity override locale for table field translations.
   */
  public function getLocale():?string
  {
    return $this->locale;
  }

  /**
   * Tweak the set of translatable fields based on the current state of the
   * entity. This can be used to remove fields. Addings fields would at least
   * brake the tree-walker as it has to start with the default set of fields.
   */
  public function filterTranslatableFields(array $fields):array
  {
    return $fields;
  }
}
