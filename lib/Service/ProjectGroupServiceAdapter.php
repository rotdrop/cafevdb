<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use Psr\Container\ContainerInterface;

/**
 * Adapter for the project-group service of the cafevdbmembers app. The
 * members app classes are resolved lazily by name, so that this app and its
 * tests work without the members app being installed.
 */
class ProjectGroupServiceAdapter implements IProjectGroupService
{
  private const MEMBERS_SERVICE_CLASS = 'OCA\\CAFeVDBMembers\\Service\\ProjectGroupService';

  private ?object $service = null;

  /** {@inheritdoc} */
  public function __construct(
    private ContainerInterface $appContainer,
  ) {
  }

  /** {@inheritdoc} */
  public function getProjectFolderLinkShare(int $projectId):array
  {
    $this->service ??= $this->appContainer->get(self::MEMBERS_SERVICE_CLASS);
    return $this->service->getProjectFolderLinkShare($projectId);
  }
}
