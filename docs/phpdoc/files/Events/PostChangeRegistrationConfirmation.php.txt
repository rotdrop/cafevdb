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
use OCP\EventDispatcher\Event;

/** ORM -> cloud event forwarding. */
class PostChangeRegistrationConfirmation extends Event
{
  /** @var Entities\ProjectParticipant */
  private $participant;

  /** @var bool */
  private $oldRegistration;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(Entities\ProjectParticipant $participant, bool $oldRegistration)
  {
    parent::__construct();
    $this->participant = $participant;
    $this->oldRegistration = $oldRegistration;
  }
  // phpcs:enable

  /** @return Entities\ProjectParticipant */
  public function getProjectParticipant():Entities\ProjectParticipant
  {
    return $this->participant;
  }

  /** @return bool */
  public function getOldRegistration():bool
  {
    return $this->oldRegistration;
  }
}
