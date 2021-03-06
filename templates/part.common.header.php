<?php
/* Orchestra member, musician and project management application.
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
if (!isset($_['navBarInfo'])) {
    $_['navBarInfo'] = '';
}

$pageRows = floor($_['pagerows'] / 10) * 10;
$pageRowsOptions = array(-1 => '&infin;');
$maxRows = 100;
for ($i = 10; $i <= $maxRows; $i += 10) {
    $pageRowsOptions[$i] = $i;
}
if ($pageRows > $maxRows) {
    $pageRows = 0;
}

$navigationControls = $pageNavigation->buttonsFromArray(
    array(
        'undo' => array(
            'name' => $l->t('Back'),
            'title' => $l->t('Navigate back to the previous view in the recorded history.'),
            'image' => image_path($appName, 'undo-solid.svg'),
            'class' => 'undo navigation history tooltip-auto',
            'id' => 'undobutton',
            'disabled' => $undoDisabled,
            'type' => 'submitbutton'),
        'reload' => array(
            'name' => $l->t('Reload'),
            'title' => $l->t('Reload the current view.'),
            'image' => array(image_path($appName, 'reload-solid.svg'),
                             image_path('core', 'loading.gif')),
            'class' => 'reload navigation history tooltip-auto',
            'id' => 'reloadbutton',
            'type' => 'submitbutton'),
        'redo' => array(
            'name' => $l->t('Next'),
            'title' => $l->t('Navigate to the next view in the recorded history.'),
            'image' => image_path($appName, 'redo-solid.svg'),
            'class' => 'redo navigation history tooltip-auto',
            'id' => 'redobutton',
            'disabled' => $redoDisabled,
            'type' => 'submitbutton'),
        'home' => array(
            'name' => $l->t('Startpage'),
            'title' => $l->t('Navigate back to the start-page.'),
            'image' => image_path($appName, 'home-solid.svg'),
            'class' => 'settings navigation home tooltip-auto',
            'id' => 'homebutton',
            'type' => 'submitbutton')));

$settingsControls = '
<input id="tooltipbutton-checkbox"
       type="checkbox"
       class="tooltips left-infinity-shift"
       '.($showToolTips == 'on' ? 'checked="checked"' :'').'>
  <div id="tooltipbutton"
       class="button tooltips-'.($showToolTips == 'on' ? 'en' : 'dis').'abled">
    <label id="tooltipbutton-label"
           for="tooltipbutton-checkbox"
           title="'.$l->t('Toggle Tooltips').'"
           class="table-cell centered tooltip-auto">
    <img src="'.image_path($appName, 'info-solid.svg').'" class="svg">
  </div>
</label>';

if (!isset($_['headerblock']) && isset($_['header'])) {
    $header = $_['header'];
} else {
    $header = '';
}

$expertClass = 'expertmode'.($expertMode != 'on' ? ' hidden' : '');

$sideBarToolTipPos = 'auto';
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
    <div id="cafevdb-navigation-info"><?php echo $_['navBarInfo']; ?></div>
    <div id="app-settings-header"
         class="tooltip-<?php echo $sideBarToolTipPos; ?>"
         title="<?php echo $toolTips['settings-button']; ?>">
      <button class="settings-button" tabindex="0"><?php echo $l->t('Settings'); ?></button>
    </div>
    <div id="app-settings-content">
      <ul class="">
        <li>
          <input id="app-settings-tooltips"
                 type="checkbox"
                 name="tooltips" <?php echo $showToolTips == 'on' ? 'checked="checked"' : ''; ?>
                 class="checkbox tooltips tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo $toolTips['show-tool-tips']; ?>"/>
          <label for="app-settings-tooltips"
                 class="tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo $toolTips['show-tool-tips']; ?>">
            <?php echo $l->t('Tool-Tips'); ?>
          </label>
        </li>
        <li>
          <input id="app-settings-filtervisibility"
                 type="checkbox"
                 name="filtervisibility" <?php echo $_['filtervisibility'] == 'on' ? 'checked="checked"' : ''; ?>
                 class="checkbox filtervisibility tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo $toolTips['filter-visibility']; ?>"/>
          <label for="app-settings-filtervisibility"
                 class="tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo $toolTips['filter-visibility']; ?>">
            <?php echo $l->t('Filter-Controls'); ?>
          </label>
        </li>
        <li>
          <input id="app-settings-directchange"
                 type="checkbox"
                 name="directchange" <?php echo $_['directchange'] == 'on' ? 'checked="checked"' : ''; ?>
                 class="checkbox directchange tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo $toolTips['direct-change']; ?>"/>
          <label for="app-settings-directchange"
                 class="tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo $toolTips['direct-change']; ?>">
            <?php echo $l->t('Quick Change-Dialog'); ?>
          </label>
        </li>
        <li class="chosen-dropup">
          <select name="pagerows"
                  data-placeholder="<?php echo $l->t('#Rows'); ?>"
                  class="table-pagerows pagerows chosen-dropup tooltip-<?php echo $sideBarToolTipPos; ?>"
                  id="app-settings-table-pagerows"
                  title="<?php echo $toolTips['table-rows-per-page']; ?>">
            <?php
            foreach($pageRowsOptions as $value => $text) {
              $selected = $value == $pageRows ? ' selected="selected"' : '';
              echo '<option value="'.$value.'"'.$selected.'>'.$text.'</option>'."\n";
            }
            ?>
          </select>
          <label for="app-settings-table-pagerows"
                 class="tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo $toolTips['table-rows-per-page']; ?>">
            <?php echo $l->t('#Rows/Page in Tables'); ?>
          </label>
        </li>
        <li>
          <input id="app-settings-expertmode"
                 type="checkbox"
                 name="expertmode" <?php echo $expertMode == 'on' ? 'checked="checked"' : ''; ?>
                 class="checkbox expertmode tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo $toolTips['expert-mode']; ?>"/>
          <label for="app-settings-expertmode"
                 class="tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo $toolTips['expert-mode']; ?>">
            <?php echo $l->t('Expert-Mode'); ?>
          </label>
        </li>
        <li class="<?php echo $expertClass; ?> expertmode-container">
          <input id="app-settings-showdisabled"
                 type="checkbox"
                 name="showdisabled" <?php echo $_['showdisabled'] == 'on' ? 'checked="checked"' : ''; ?>
                 class="checkbox showdisabled tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo $toolTips['show-disabled']; ?>"/>
          <label for="app-settings-showdisabled"
                 class="tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo $toolTips['show-disabled']; ?>">
            <?php echo $l->t('Show Disabled Data-Sets'); ?>
          </label>
        </li>
        <li class="<?php echo $expertClass; ?> expertmode-container chosen-dropup">
          <?php echo $this->inc('settings/part.debug-mode', [ 'toolTipsPos' => $sideBarToolTipPos ]); ?>
        </li>
        <li>
          <a id="app-settings-further-settings"
             class="settings generalsettings tooltip-<?php echo $sideBarToolTipPos; ?>"
             title="<?php echo $toolTips['further-settings']; ?>"
             href="#">
            <?php echo $l->t('Further Settings'); ?>
          </a>
        </li>
      </ul>
    </div>
  </div>
</div>
<div id="app-content">
  <div id="app-inner-content">
    <form id="personalsettings" class="visible personal-settings" method="post" action="?app=<?php echo $_['appName']; ?>">
      <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>" />
      <input type="hidden" name="template" value="<?php p($template); ?>" />
      <?php echo $navigationControls; ?>
      <div class="buttonseparator"></div>
      <?php echo $settingsControls; ?>
    </form>
    <div class="cafevdb-general" data-snap-ignore="true" id="cafevdb-general"><?php/* used to eliminate the pixel-size of the control bar */?>
      <?php echo isset($_['headerblock']) ? '<!-- ' : ''; ?>
      <div id="<?php echo $css_pfx; ?>-header-box" class="<?php echo $css_pfx; ?>-header-box <?php echo $css_class; ?>">
        <div id="<?php echo $css_pfx; ?>-header" class="<?php echo $css_pfx; ?>-header <?php echo $css_class; ?>">
          <?php echo $header; ?>
        </div>
      </div>
      <?php echo isset($_['headerblock']) ? ' -->' : ''; ?>
      <?php echo isset($_['headerblock']) ? $_['headerblock'] : ''; ?>
      <div id="<?php echo $css_pfx; ?>-container" class="<?php echo $css_pfx; ?>-container <?php echo $css_class; ?>"> <!-- used to have something with 100 height for scrollbars -->
        <div id="<?php echo $css_pfx; ?>-body" class="<?php echo $css_pfx; ?>-body <?php echo $css_class; ?>">
          <div id="<?php echo $css_pfx; ?>-body-inner" class="<?php echo $css_pfx; ?>-body-inner <?php echo $css_class; ?>">
