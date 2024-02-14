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

namespace OCA\CAFEVDB\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Toolkit\Traits\AuthenticatedCommandTrait as ToolkitTrait;

/** Trait in order to handle authentication with the cloud */
trait AuthenticatedCommandTrait
{
  use ToolkitTrait {
    ToolkitTrait::authenticate as toolkitAuthenticate;
  }
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /**
   * {@inheritdoc}
   *
   * @see execute()
   */
  protected function authenticate(InputInterface $input, OutputInterface $output):int
  {
    if ($this->toolkitAuthenticate($input, $output) === 0) {
      $this->entityManager = $this->appContainer->get(EntityManager::class);
      return 0;
    }
    return 1;
  }
}
