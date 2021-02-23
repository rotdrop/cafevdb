<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB;

script($appName, 'settings');
style($appName, 'settings');

$tooltipstitle  = $toolTips['show-tool-tips'];
$filtervistitle = $toolTips['filter-visibility'];
$directchgtitle = $toolTips['direct-change'];
$showdistitle   = $toolTips['show-disabled'];
$pagerowstitle  = $toolTips['table-rows-per-page'];
$wysiwygtitle   = $toolTips['settings-wysiwyg-editor'];
$experttitle    = $toolTips['expert-operations'];
$debugtitle     = $toolTips['debug-mode'];

$pageRows = floor($_['pagerows'] / 10) * 10;
$pageRowsOptions = array(-1 => '&infin;');
$maxRows = 100;
for ($i = 10; $i <= $maxRows; $i += 10) {
  $pageRowsOptions[$i] = $i;
}
if ($pageRows > $maxRows) {
  $pageRows = 0;
}

date_default_timezone_set($timezone);
$timestamp = strftime('%Y%m%d-%H%M%S');
$oldlocale = setlocale(LC_TIME, $locale);
$time = strftime('%x %X');
setlocale(LC_TIME, $oldlocale);

$expertClass = $expertMode == 'on' ? '' : ' hidden';
$toolTipClass = "tooltip-right";

?>
<div id="personal-settings-container" class="app-admin-settings hidden">
  <div class="popup-title">
    <h2><?php p($l->t('Settings')); ?></h2>
  </div>
  <div class="popup-content">
    <ul id="adminsettingstabs">
      <li><a href="#tabs-1"><?php p($l->t('Personal')); ?></a></li>
      <?php $tabNo = 2; if ($_['adminsettings']) { ?>
        <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php p($l->t('General')); ?></a></li>
        <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php p($l->t('Orchestra')); ?></a></li>
        <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php p($l->t('Sharing')); ?></a></li>
        <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php p($l->t('Email')); ?></a></li>
        <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php p($l->t('CMS')); ?></a></li>
        <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php p($l->t('Translations')); ?></a></li>
        <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php p($l->t('Development')); ?></a></li>
      <?php } ?>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php p($l->t('?')); ?></a></li>
    </ul>
    <?php
    $tabNo = 1;
    echo $this->inc("settings/personal-settings", [ 'tabNr' => $tabNo++ ]);
    if ($adminsettings === true) {
      echo $this->inc("settings/app-settings", [ 'tabNr' => $tabNo++ ]);
      echo $this->inc("settings/orchestra-settings", [ 'tabNr' => $tabNo++ ]);
      echo $this->inc("settings/share-settings", [ 'tabNr' => $tabNo++ ]);
      echo $this->inc("settings/email-settings", [ 'tabNr' => $tabNo++ ]);
      echo $this->inc("settings/cms-settings", [ 'tabNr' => $tabNo++ ]);
      echo $this->inc("settings/translations", [ 'tabNr' => $tabNo++ ]);
      echo $this->inc("settings/devel-settings", [ 'tabNr' => $tabNo++ ]);
    }
    echo $this->inc("settings/about", [ 'tabNr' => $tabNo++ ]);
    ?>
  </div>
</div>
