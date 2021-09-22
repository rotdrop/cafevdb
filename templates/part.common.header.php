<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Service\ConfigService;

/******************************************************************************
 *
 * Emit all scripts. $renderAs == 'blank' will avoid scripts and styles.
 *
 */

script($appName, 'app');
style($appName, 'app');

/*
 *
 *****************************************************************************/

$template  = $_['template'];
$css_pfx   = $_['css-prefix'];
$css_class = $template;
if (isset($_['css-class'])) {
    $css_class .= ' '.$_['css-class'];
}

$redoDisabled = $_['historyPosition'] == 0;
$undoDisabled = $_['historySize'] - $_['historyPosition'] <= 1;

$navigationControls = $pageNavigation->buttonsFromArray(
  [
    'undo' => [
      'name' => $l->t('Back'),
      'title' => $l->t('Navigate back to the previous view in the recorded history.'),
      'image' => image_path($appName, 'undo-solid.svg'),
      'class' => 'undo navigation history tooltip-auto',
      'id' => 'undobutton',
      'disabled' => $undoDisabled,
      'type' => 'submitbutton',
    ],
    'reload' => [
      'name' => $l->t('Reload'),
      'title' => $l->t('Reload the current view.'),
      'image' => [
	image_path($appName, 'reload-solid.svg'),
	image_path('core', 'loading.gif'),
      ],
      'class' => 'reload navigation history tooltip-auto',
      'id' => 'reloadbutton',
      'type' => 'submitbutton',
    ],
    'redo' => [
      'name' => $l->t('Next'),
      'title' => $l->t('Navigate to the next view in the recorded history.'),
      'image' => image_path($appName, 'redo-solid.svg'),
      'class' => 'redo navigation history tooltip-auto',
      'id' => 'redobutton',
      'disabled' => $redoDisabled,
      'type' => 'submitbutton',
    ],
    'home' => [
      'name' => $l->t('Startpage'),
      'title' => $l->t('Navigate back to the start-page.'),
      'image' => image_path($appName, 'home-solid.svg'),
      'class' => 'settings navigation home tooltip-auto',
      'id' => 'homebutton',
      'type' => 'submitbutton',
      // 'method' => 'post',
    ],
  ]);

$settingsControls = '
<input id="tooltipbutton-checkbox"
       type="checkbox"
       class="tooltips left-infinity-shift"
       '.($showToolTips == 'on' ? 'checked="checked"' :'').'/>
<div id="tooltipbutton"
     class="button tooltips-'.($showToolTips == 'on' ? 'en' : 'dis').'abled">
  <label id="tooltipbutton-label"
         for="tooltipbutton-checkbox"
         title="'.$l->t('Toggle Tooltips').'"
         class="table-cell centered tooltip-auto">
    <img src="'.image_path($appName, 'info-solid.svg').'" class="svg">
  </label>
</div>';

if (!isset($_['headerblock']) && isset($_['header'])) {
    $header = $_['header'];
} else {
    $header = '';
}

?>

<div id="app-navigation" class="personal-settings app-navigation snapper-enabled">
  <ul id="navigation-list">
    <li class="nav-heading">
      <a class="nav-heading" href="#">
        <?php echo $l->t('Close Side-Bar Menu'); ?>
      </a>
    </li>
    <?php echo $_['navigationcontrols']; ?>
  </ul>
  <div id="app-settings">
    <?php echo $this->inc('settings/part.side-menu-settings', []); ?>
  </div>
</div>
<div id="app-content">
  <div id="app-inner-content">
    <form id="personalsettings" class="visible personal-settings" method="post" action="?app=<?php echo $_['appName']; ?>">
      <?php echo $navigationControls; ?>
      <div class="buttonseparator"></div>
      <?php echo $settingsControls; ?>
    </form>
    <div class="cafevdb-general" data-snap-ignore="true" id="cafevdb-general"><!-- /* used to eliminate the pixel-size of the control bar -->
      <?php echo isset($_['headerblock']) ? '<!-- ' : ''; ?>
      <div id="<?php echo $css_pfx; ?>-header-box" class="<?php echo $css_pfx; ?>-header-box <?php echo $css_class; ?>">
        <div id="<?php echo $css_pfx; ?>-header" class="<?php echo $css_pfx; ?>-header <?php echo $css_class; ?>">
          <?php echo $header; ?>
        </div>
      </div>
      <?php echo isset($_['headerblock']) ? ' -->' : ''; ?>
      <?php echo isset($_['headerblock']) ? $_['headerblock'] : ''; ?>
      <div id="<?php echo $css_pfx; ?>-container" class="<?php echo $css_pfx; ?>-container <?php echo $css_class; ?>"> <!-- used to have something with 100% height for scrollbars -->
        <div id="<?php echo $css_pfx; ?>-body" class="<?php echo $css_pfx; ?>-body <?php echo $css_class; ?>">
          <div id="<?php echo $css_pfx; ?>-body-inner" class="<?php echo $css_pfx; ?>-body-inner <?php echo $css_class; ?>">
