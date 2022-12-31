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

/** Event forwarder ORM -> cloud. */
class PreChangeUserIdSlug extends Event
{
  /** @var Entities\Musician */
  private $musician;

  /** @var string */
  private $oldSlug;

  /** @var string */
  private $newSlug;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(Entities\Musician $musician, string $oldSlug, string $newSlug)
  {
    parent::__construct();
    $this->musician = $musician;
    $this->oldSlug = $oldSlug;
    $this->newSlug = $newSlug;
  }
  // phpcs:enable

  /** @return Entities\Musician */
  public function getMusician():Entities\Musician
  {
    return $this->musician;
  }

  /** @return string */
  public function getOldSlug():string
  {
    return $this->oldSlug;
  }

  /** @return string */
  public function getNewSlug():string
  {
    return $this->newSlug;
  }
}
