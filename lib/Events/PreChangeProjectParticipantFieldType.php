<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Events;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCP\EventDispatcher\Event;

use OCA\CAFEVDB\Enums\EnumParticipantFieldDataType as FieldType;

/** Event forwarder ORM -> cloud. */
class PreChangeProjectParticipantFieldType extends Event
{
  /** @var Entities\ProjectParticipantField */
  private $field;

  /** @var null|FieldType */
  private $oldType;

  /** @var null|FieldType */
  private $newType;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(Entities\ProjectParticipantField $field, ?FieldType $oldType, ?FieldType $newType)
  {
    parent::__construct();
    $this->field = $field;
    $this->oldType = $oldType;
    $this->newType = $newType;
  }
  // phpcs:enable

  /** @return Entities\ProjectParticipantField */
  public function getField():Entities\ProjectParticipantField
  {
    return $this->field;
  }

  /** @return FieldType */
  public function getOldType():FieldType
  {
    return $this->oldType;
  }

  /** @return FieldType */
  public function getNewType():FieldType
  {
    return $this->newType;
  }
}
