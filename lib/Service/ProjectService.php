<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

class ProjectService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var DatabaseService */
  protected $databaseService;

  public function __construct(ConfigService $configService, DatabaseService $databaseService)
  {
    $this->configService = $configService;
    $this->databaseService = $databaseService;
  }

  /**Generate an option table with all participants, suitable to be
   * staffed into Navigation::selectOptions(). This is a single
   * select, only one musician may be preselected. The key is the
   * musician id. The options are meant for a single-choice select box.
   *
   * @param $projectId The id of the project to fetch the musician options from
   *
   * @param $projectName Optional project name, will be queried from
   * DB if not specified.
   *
   * @param $musicianId A pre-selected musician, defaults to null.
   */
  public function participantOptions($projectId, $projectName = null, $musicianId = -1)
  {
    if (empty($projectName)) {
      $projectName = $this->fetchName($projectId);
    }

    $table = $projectName.'View';

    $options = [];

    // simply fetch all participants
    $query = "SELECT Name,Vorname,MusikerId FROM ? WHERE 1";

    $stmt = $this->databaseService->executeQuery($query, [$table]);
    while($row = $stmt->fetch()) {
      $key = $row['MusikerId'];
      $name = $row['Vorname'].' '.$row['Name'];
      $flags = ($key == $musicianId) ? Navigation::SELECTED : 0;
      $options[] = [ 'value' => $key,
                     'name' => $name,
                     'flags' => $flags ];
    }

    return $options;
  }

  /** Fetch the project-name name corresponding to $projectId.
   */
  public function fetchName($projectId)
  {
    $result = $this->databaseService->fetchAll(
      'SELECT Name FROM Projekte WHERE Id = ?',
      [$projectId]);

    if (count($result) == 1 && !empty($result[0]['Name'])) {
      return $result[0]['Name'];
    }

    return null;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
