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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Translatable\Entity\MappedSuperclass\AbstractTranslation;

/**
 * TableFieldTranslations
 *
 * Table to store translations of certain other database table fields
 * (in contrast to storing source-code translations, @see
 * TranslationKey entity).
 *
 * @ORM\Table(name="TableFieldTranslations",
 *   options={"row_format":"DYNAMIC"},
 *   indexes={
 *     @ORM\Index(name="translations_lookup_idx", columns={
 *       "locale", "object_class", "foreign_key"
 *   })},
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(name="lookup_unique_idx", columns={
 *       "locale", "object_class", "field", "foreign_key"
 *   })}
 * )
 * @ORM\Entity(repositoryClass="OCA\CAFEVDB\Wrapped\Gedmo\Translatable\Entity\Repository\TranslationRepository")
 */
class TableFieldTranslation extends AbstractTranslation implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
  }
  // phpcs:enable
}
