<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2024 Claus-Justus Heine
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

use Throwable;

use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;

/**
 * Traits class for visualizing the user avatar for musicians which also have
 * a cloud account.
 *
 * @param IAvatarManager $avatarManager
 */
trait MusicianAvatarTrait
{
  use QueryFieldTrait;

  /** @var ToolTipsService */
  protected $toolTipsService;

  /**
   * @param string $tableTab The id of the column-group the fields should be
   * attached to.
   *
   * @param array $css Additional css-classes to add to the field definition
    * HTML entities.
   *
   * @return array
   */
  public function renderMusicianAvatarField(
    string $tableTab,
    array $css = [],
  ):array {
    $joinStructure = [];

    $generator = function(&$fdd) use ($tableTab, $css) {

      $fdd['avatar'] = [
        'tab' => ['id' => $tableTab],
        'input' => 'V',
        'name' => $this->l->t('Avatar'),
        'select' => 'T',
        'options' => 'LFACPDV',
        'css'      => [
          'postfix' => array_merge([
            'cloud-avatar',
            'tooltip-top',
          ], $css),
        ],
        'sql' => $this->joinTables[self::MUSICIANS_TABLE] . '.user_id_slug',
        'php' => function($userIdSlug, $action, $k, $row, $recordId, PHPMyEdit $pme) {
          $joinTable = !empty($pme->fdn[self::joinTableMasterFieldName(self::MUSICIANS_TABLE)]);
          $columnName = 'cloud_account_disabled';
          $column = $joinTable ? $this->joinQueryField(self::MUSICIANS_TABLE, $columnName) : $this->queryField($columnName);
          if ($row[$column]) {
            // no need to bother the cloud, the user is not there.
            return '';
          }
          $imageSize = $this->listOperation() ? 24 : 256;
          try {
            $avatarUrl = $this->urlGenerator()->linkToRoute('core.avatar.getAvatar', [ 'userId' => $userIdSlug, 'size' => $imageSize ]);
          } catch (Throwable $t) {
            return '';
          }
          return '<div class="flex-container"><img height="' . $imageSize . '" src="' . $avatarUrl . '?v=2"></img></div>';
        },
        'default' => '',
        'sort' => false,
        'tooltip' => $this->toolTipsService['page-renderer:musicians:avatar'],
      ];
    };

    return [ $joinStructure, $generator ];
  }
}
