<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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

use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Common\UndoableFolderRename;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener as TranslatableListener;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * ProjectParticipantFields
 *
 * @ORM\Table(
 *   name="ProjectParticipantFieldAttributes"
 * )
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 *
 * Soft deletion is necessary in case the ProjectPayments table
 * already contains entries.
 */
class ProjectParticipantFieldAttribute implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;

  /**
   * @var ProjectParticipantField
   *
   * @ORM\ManyToOne(targetEntity="ProjectParticipantField", inversedBy="fieldData", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $field;

  /**
   * @var Types\EnumParticipantFieldAttribute
   *
   * @ORM\Column(type="EnumParticipantFieldAttribute", length=128, options={"collation"="ascii_general_ci"})
   * @ORM\Id
   */
  private $name;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length="1024", nullable=false)
   */
  private $value;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->__wakeup();
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function __clone()
  {
    if (!$this->id) {
      return;
    }
  }

  /** {@inheritdoc} */
  public function __wakeup()
  {
    $this->arrayCTOR();
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    return $this->name . '@' . $this->id . '[' . $this->field->getName() . ':' . $this->value . ']';
  }

  /**
   * Set field.
   *
   * @param null|int|ProjectParticipantField $field
   *
   * @return ProjectParticipantFieldAttribute
   */
  public function setField(mixed $field):ProjectParticipantFieldAttribute
  {
    $this->field = $field;

    return $this;
  }

  /**
   * Get field.
   *
   * @return ProjectParticipantField
   */
  public function getField():ProjectParticipantField
  {
    return $this->field;
  }

  /**
   * Set name.
   *
   * @param null|string $name
   *
   * @return ProjectParticipantFieldAttribute
   */
  public function setName(?string $name):ProjectParticipantFieldAttribute
  {
    $this->name = $name;

    return $this;
  }

  /**
   * Get name.
   *
   * @return Types\EnumParticipantFieldAttributex
   */
  public function getName():Types\EnumParticipantFieldAttribute
  {
    return $this->name;
  }

  /**
   * Set value.
   *
   * @param null|string $value
   *
   * @return ProjectParticipantFieldAttribute
   */
  public function setValue(?string $value):ProjectParticipantFieldAttribute
  {
    $this->value = $value;

    return $this;
  }

  /**
   * Get value.
   *
   * @return null|string
   */
  public function getValue():?string
  {
    return $this->value;
  }
}
