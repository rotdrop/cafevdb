<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023 Claus-Justus Heine
 * @license GNU AGPL version 3 or any later version
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
 * You should have received a copy of the GNU Affero General Public
 * License alogng with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\Service\ToolTipsService;

/**
 * @param string $folder The file-system path of the shared folder.
 * @param string $filesAppLink Link to the shared folder in the cloud file-system (if the folder exits)
 * @param ToolTipsService $toolTips
 * @param string $operation
 */

$toolTipsPrefix = 'page-renderer:projects:posters:';
$filesAppTarget = md5($folder);

?>
<div class="tooltip-auto pme-cell-wrapper flex-container flex-center flex-justify-start">
  <a href="<?php echo $filesAppLink; ?>" target="<?php echo $filesAppTarget; ?>"
     title="<?php echo $toolTips[$toolTipsPrefix . 'open-cloud']; ?>"
     class="not-empty open-cloud tooltip-auto<?php empty($filesAppLink) && p(' disabled'); ?>"
     target="' . $filesAppTarget . '"
  >
    <?php p($l->t('Project Posters and related Marketing Media')); ?>
  </a>
</div>
