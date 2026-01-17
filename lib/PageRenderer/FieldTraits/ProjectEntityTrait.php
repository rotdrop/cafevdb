<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use InvalidArgumentException;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/**
 * Provide a findProject() method which initializes $this->project as project
 * entity if either the project name or id is given.
 */
trait ProjectEntityTrait
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  protected ?int $projectId;

  protected ?string $projectName;

  protected ?Entities\Project $project;

  /**
   * Initialize $this->project as project entity if either the project name or
   * id is given.
   *
   * @param bool $enforce Throw an InvalidArgumentException if the project cannot be found.
   *
   * @return null|Entities\Project
   *
   * @throws InvalidArgumentException
   */
  protected function findProject(bool $enforce = false): ?Entities\Project
  {
    if ($this->projectId > 0) {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($this->projectId);
      $this->projectName = $this->project->getName();
    } elseif (!empty($this->projectName)) {
      $this->project = $this->getDatabaseRepository(Entities\Project::class)->findOneBy([ 'name' => $this->projectName ]);
      $this->projectId = $this->project->getId();
    }

    if ($enforce && empty($this->project)) {
      if ($this->projectId <= 0 && empty($this->projectName)) {
        throw new InvalidArgumentException(
          $this->l->t(
            'Project-id and/or -name must be given (%1$s / %2$s).',
            [ $this->projectName, $this->projectId ],
          ),
        );
      } else {
        throw new Exceptions\DatabaseEntityNotFoundException(
          $this->l->t(
            'Unable to fetch the project %1$s / %2$s from the database.',
            [ $this->projectName, $this->projectId ],
          ),
        );
      }
    }

    return $this->project ?? null;
  }
}
