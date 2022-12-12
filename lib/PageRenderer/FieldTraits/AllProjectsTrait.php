<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\PMETableViewBase as BaseRenderer;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;

/** Add for fun a field with all projects the musician already participated in. */
trait AllProjectsTrait
{
  protected static $allProjectsTable = BaseRenderer::PROJECT_PARTICIPANTS_TABLE . BaseRenderer::VALUES_TABLE_SEP . 'allProjects';

  /**
   * @param string $musicianIdField The field name of the column with the
   * musician id. If null assume that the musicians table is the master table
   * in the join and the column name is 'id'.
   *
   * @param string $tableTab The id of the column-group the fields should be
   * attached to.
   *
   * @param array $css Additional css-classes to add to the field definition
   * HTML entities.
   *
   * @return array
   */
  public function renderAllProjectsField(
    string $musicianIdField,
    string $tableTab,
    array $css = [],
  ):array {

    $joinStructure = [
      // in order to get the participation in all projects
      self::$allProjectsTable => [
        'entity' => Entities\ProjectParticipant::class,
        'sql' => 'SELECT
  pp.musician_id AS musician_id,
  GROUP_CONCAT(
    DISTINCT p.name
    ORDER BY
      p.year ASC,
      p.name ASC SEPARATOR ","
  ) AS projects
  FROM ProjectParticipants pp
  LEFT JOIN Projects p
  ON p.id = pp.project_id
  GROUP BY pp.musician_id',
        'identifier' => [
          'musician_id' => $musicianIdField,
        ],
        'column' => 'musician_id',
        'flags' => self::JOIN_READONLY,
      ],
    ];

    $generator = function(&$fdd) use ($musicianIdField, $tableTab, $css) {

      $this->makeJoinTableField(
        $fdd, self::$allProjectsTable, 'projects', [
          'tab' => ['id' => $tableTab ],
          'input' => 'VR',
          'options' => 'LFDVC',
          'select' => 'M',
          'name' => $this->l->t('Projects'),
          'sort' => true,
          'css'      => [
            'postfix' => array_merge([
              'projects',
              'tooltip-top',
            ], $css),
          ],
          'display|LDCVF' => ['popup' => 'data'],
          'sql' => $this->joinTables[self::$allProjectsTable] . '.projects',
          'sql|CDV' => "REPLACE(" . $this->joinTables[self::$allProjectsTable] . '.projects' . ", ',', ', ')",
          'filter' => [
            'having' => false,
            'flags' => PHPMyEdit::OMIT_SQL|PHPMyEdit::OMIT_DESC,
          ],
          'values' => [
            'table' => BaseRenderer::PROJECTS_TABLE,
            'column' => 'name',
            'orderby' => '$table.year ASC, $table.name ASC',
            'groups' => 'year',
            'join' => false,
          ],
        ]);
    };

    return [ $joinStructure, $generator ];
  }
}
