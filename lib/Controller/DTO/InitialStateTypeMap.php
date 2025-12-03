<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022-2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller\DTO;

/**
 * Mapping initial state keys to initial state DTOs. This is not to be
 * instantiated. On the TypeScript side using string literal type make it
 * possible to deduce the type of the initial state record from the used
 * initial state key.
 */
class InitialStateTypeMap extends \OCA\CAFEVDB\Toolkit\DTO\AbstractDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly CAFEVDBInitialState $CAFEVDB,
    public readonly PMEInitialState $PHPMyEdit,
    public readonly FilesInitialState $files,
  ) {
  }
}
