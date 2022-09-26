<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine
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

/**
 * Event fired by the Musician entity through a life-cycle hook before the
 * email address is changed.
 */
class PreChangeMusicianEmail extends MusicianEmailEvent
{
  /** @var Entities\MusicianEmailAddress */
  private $newEmail;

  /**
   * @param Entities\MusicianEmailAddress $oldEmail The old address.
   *
   * @param Entities\MusicianEmailAddress $newEmail The new address.
   */
  public function __construct(
    Entities\MusicianEmailAddress $oldEmail,
    Entities\MusicianEmailAddress $newEmail,
  ) {
    parent::__construct($oldEmail);
    $this->newEmail = $newEmail;
  }

  /**
   * Getter for the musician entity.
   *
   * @return Entities\Musician
   */
  public function getMusician():Entities\Musician
  {
    return parent::getEntity()->getMusician();
  }

  /**
   * Getter for the old email address.
   *
   * @return Entities\MusicianEmailAddress
   */
  public function getOldEmail():Entities\MusicianEmailAddress
  {
    return parent::getEntity();
  }

  /**
   * Getter for the new email address.
   *
   * @return Entities\MusicianEmailAddress
   */
  public function getNewEmail():Entities\MusicianEmailAddress
  {
    return $this->newEmail;
  }
}
