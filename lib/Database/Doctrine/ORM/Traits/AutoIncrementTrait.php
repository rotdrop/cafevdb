<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024-2026 Claus-Justus Heine
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

/**
 * Define an auto-increment field with name 'id', including getter and setter.
 */
trait AutoIncrementTrait
{
  /**
   * This must be protected as otherwise inheritance is not possible:
   * althought the consuming class can change the visibility of a method, it
   * may not change the visibility of a property.
   */
  #[ORM\Column(type: 'integer', nullable: false)]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'IDENTITY')]
  protected ?int $id = null;

  /**
   * Set the id. Enforce null for 0 as this is what ORM expects.
   *
   * @param ?int $id
   *
   * @return self
   */
  public function setId(?int $id): self
  {
    $this->id = $id ? $id : null;

    return $this;
  }

  /**
   * Get id.
   *
   * @return null|int
   */
  public function getId(): ?int
  {
    return $this->id ? $this->id : null;
  }
}
