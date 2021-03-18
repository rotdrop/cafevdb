<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use Gedmo\Translatable\Entity\MappedSuperclass\AbstractTranslation;

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
 * @ORM\Entity(repositoryClass="Gedmo\Translatable\Entity\Repository\TranslationRepository")
 */
class TableFieldTranslation extends AbstractTranslation implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  public function __construct() {
    $this->arrayCTOR();
  }
}
