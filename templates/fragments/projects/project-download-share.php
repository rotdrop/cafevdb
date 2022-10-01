<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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
 * @param string $share The url to show.
 * @param string $folder The file-system path of the shared folder.
 * @param string $filesAppLink Link to the shared folder in the cloud file-system (if the folder exits)
 * @param string $expirationDate Formatted expiration date of the link.
 * @param ToolTipsService $toolTips
 * @param string $operation
 */

$toolTipsPrefix = 'page-renderer:projects:public-downloads:';
$dataToolTip = implode('<br/>', array_filter([ $folder, $share, $expirationDate ]));
$filesAppTarget = md5($folder);

?>

<div class="tooltip-auto pme-cell-wrapper flex-container flex-center flex-justify-start <?php p(empty($share) ? 'empty' : 'has-content'); ?>">
  <a href="#" class="only-empty not-list-operation create-share-link button button-use-icon operation tooltip-auto"
     title="<?php echo $toolTips[$toolTipsPrefix . 'create']; ?>"
  >
    <?php p($l->t('create share link')); ?>
  </a>
  <a href="#" class="not-empty not-list-operation delete-share-link button button-use-icon operation tooltip-auto"
     title="<?php echo $toolTips[$toolTipsPrefix . 'delete']; ?>"
  >
    <?php p($l->t('delete share link')); ?>
  </a>
  <a class="url external not-empty tooltip-top tooltip-wide"
     target="<?php p(md5($share)); ?>"
     title="<?php p($dataToolTip); ?>"
     href="<?php p($share); ?>">
    <div class="nav content pme-cell-wrapper pme-cell-squeezer pme-style-all-views one-liner ellipsis medium-width clip-long-text">
      <?php p($share); ?>
    </div>
  </a>
  <a class="not-empty copy-to-clipboard button button-use-icon operation tooltip-auto"
     title="<?php echo $toolTips[$toolTipsPrefix . 'clipboard']; ?>""
     href="#">
    <?php p($l->t('copy to clipboard')); ?>
  </a>
  <a href="<?php echo $filesAppLink; ?>" target="<?php echo $filesAppTarget; ?>"
     title="<?php echo $toolTips[$toolTipsPrefix . 'open-cloud']; ?>"
     class="not-empty open-cloud button button-use-icon operation tooltip-auto<?php empty($filesAppLink) && p(' disabled'); ?>"
  ></a>
  <input type="text"
         class="date not-empty not-pme-list share-expiration-date"
         value="<?php p($expirationDate); ?>"
         size="11"
         placeholder="<?php p($l->t('e.g. 29.04.2032')); ?>"
         title="<?php echo $toolTips[$toolTipsPrefix . 'expiration-date']; ?>"
  />
</div>
