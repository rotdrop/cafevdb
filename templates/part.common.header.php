<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace CAFEVDB {

  // control buttons could live outside control bar and thus overlay
  // the modal overlay. Would be nice, especially when including
  // restoration of dialogs into the history management.

  echo Util::emitExternalScripts();
  echo Util::emitInlineScripts();

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

  $debugModes = array('general' => L::t('General Information'),
                      'query' => L::t('SQL Queries'),
                      'request' => L::t('HTTP Request'),
                      'tooltips' => L::t('Missing Context Help'),
                      'emailform' => L::t('Mass Email Form'));

  $navigationControls = Navigation::buttonsFromArray(
    array(
      'undo' => array(
        'name' => L::t('Back'),
        'title' => L::t('Navigate back to the previous view in the recorded history.'),
        'image' => \OCP\Util::imagePath('cafevdb', 'undo-solid.svg'),
        'class' => 'undo navigation history',
        'id' => 'undobutton',
        'disabled' => $undoDisabled,
        'type' => 'submitbutton'),
      'reload' => array(
        'name' => L::t('Reload'),
        'title' => L::t('Reload the current view.'),
        'image' => array(\OCP\Util::imagePath('cafevdb', 'reload-solid.svg'),
                         \OCP\Util::imagePath('core', 'loading.gif')),
        'class' => 'reload navigation history',
        'id' => 'reloadbutton',
        'type' => 'submitbutton'),
      'redo' => array(
        'name' => L::t('Next'),
        'title' => L::t('Navigate to the next view in the recorded history.'),
        'image' => \OCP\Util::imagePath('cafevdb', 'redo-solid.svg'),
        'class' => 'redo navigation history',
        'id' => 'redobutton',
        'disabled' => $redoDisabled,
        'type' => 'submitbutton'),
      'home' => array(
        'name' => L::t('Startpage'),
        'title' => L::t('Navigate back to the start-page.'),
        'image' => \OCP\Util::imagePath('cafevdb', 'home-solid.svg'),
        'class' => 'settings navigation home',
        'id' => 'homebutton',
        'type' => 'submitbutton')));

  $settingsControls = Navigation::buttonsFromArray(
    array(
      'tooltips' => array(
        'name' => L::t('Tooltip Button'),
        'title' => L::t('Toggle Tooltips'),
        'image' => \OCP\Util::imagePath('cafevdb', 'info-solid.svg'),
        'class' => 'help tooltips tooltip-bottom tooltips-'.($_['tooltips'] == 'on' ? 'en' : 'dis').'abled',
        'id' => 'tooltipbutton')
      ));

  if (!isset($_['headerblock']) && isset($_['header'])) {
    $header = $_['header'];
  } else {
    $header = '';
  }

  $expertClass = 'expertmode'.($_['expertmode'] != 'on' ? ' hidden' : '');

  $sideBarToolTipPos = 'right';
?>

<div id="app-navigation" class="app-navigation snapper-enabled">
  <ul id="navigation-list">
    <li class="nav-heading">
      <a href="#">
        <?php echo L::t('Close Side-Bar Menu'); ?>
      </a>
    </li>
    <?php echo $_['navigationcontrols']; ?>
  </ul>
  <div id="app-settings">
    <div id="cafevdb-navigation-info"><?php echo $_['navBarInfo']; ?></div>
    <div id="app-settings-header"
         class="tooltip-<?php echo $sideBarToolTipPos; ?>"
         title="<?php echo Config::toolTips('settings-button'); ?>">
      <button class="settings-button" tabindex="0"></button>
    </div>
    <div id="app-settings-content">
      <ul>
        <li>
          <input id="app-settings-tooltips"
                 type="checkbox"
                 name="tooltips" <?php echo $_['tooltips'] == 'on' ? 'checked="checked"' : ''; ?>
                 class="checkbox tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo Config::toolTips('show-tool-tips'); ?>"/>
          <label for="app-settings-tooltips"
                 class="tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo Config::toolTips('show-tool-tips'); ?>">
            <?php echo L::t('Tool-Tips'); ?>
          </label>
        </li>
        <li>
          <input id="app-settings-filtervisibility"
                 type="checkbox"
                 name="filtervisibility" <?php echo $_['filtervisibility'] == 'on' ? 'checked="checked"' : ''; ?>
                 class="checkbox tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo Config::toolTips('filter-visibility'); ?>"/>
          <label for="app-settings-filtervisibility"
                 class="tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo Config::toolTips('filter-visibility'); ?>">
            <?php echo L::t('Filter-Controls'); ?>
          </label>
        </li>
        <li>
          <input id="app-settings-directchange"
                 type="checkbox"
                 name="directchange" <?php echo $_['directchange'] == 'on' ? 'checked="checked"' : ''; ?>
                 class="checkbox tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo Config::toolTips('direct-change'); ?>"/>
          <label for="app-settings-directchange"
                 class="tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo Config::toolTips('direct-change'); ?>">
            <?php echo L::t('Quick Change-Dialog'); ?>
          </label>
        </li>
        <li class="<?php echo $expertClass; ?>">
          <input id="app-settings-showdisabled"
                 type="checkbox"
                 name="showdisabled" <?php echo $_['showdisabled'] == 'on' ? 'checked="checked"' : ''; ?>
                 class="checkbox tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo Config::toolTips('show-disabled'); ?>"/>
          <label for="app-settings-showdisabled"
                 class="tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo Config::toolTips('show-disabled'); ?>">
            <?php echo L::t('Show Disabled Data-Sets'); ?>
          </label>
        </li>
        <li>
          <input id="app-settings-expertmode"
                 type="checkbox"
                 name="expertmode" <?php echo $_['expertmode'] == 'on' ? 'checked="checked"' : ''; ?>
                 class="checkbox tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo Config::toolTips('expert-mode'); ?>"/>
          <label for="app-settings-expertmode"
                 class="tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo Config::toolTips('expert-mode'); ?>">
            <?php echo L::t('Expert-Mode'); ?>
          </label>
        </li>
        <li class="chosen-dropup">
          <select name="pagerows"
                  data-placeholder="<?php echo L::t('#Rows'); ?>"
                  class="table-pagerows chosen-dropup tooltip-<?php echo $sideBarToolTipPos; ?>"
                  id="app-settings-table-pagerows"
                  title="<?php echo Config::toolTips('table-rows-per-page'); ?>">
            <?php
            foreach($pageRowsOptions as $value => $text) {
              $selected = $value == $pageRows ? ' selected="selected"' : '';
              echo '<option value="'.$value.'"'.$selected.'>'.$text.'</option>'."\n";
            }
            ?>
          </select>
          <label for="app-settings-table-pagerows"
                 class="tooltip-<?php echo $sideBarToolTipPos; ?>"
                 title="<?php echo Config::toolTips('table-rows-per-page'); ?>">
            <?php echo L::t('#Rows/Page in Tables'); ?>
          </label>
        </li>
        <li>
          <a id="app-settings-further-settings"
             class="settings generalsettings tooltip-<?php echo $sideBarToolTipPos; ?>"
             title="<?php echo Config::toolTips('further-settings'); ?>"
             href="#">
            <?php echo L::t('Further Settings'); ?>
          </a>
        </li>
        <li class="<?php echo $expertClass; ?>">
          <a id="app-settings-expert-operations"
             class="settings expertoperations tooltip-<?php echo $sideBarToolTipPos; ?>"
             title="<?php echo Config::toolTips('expert-operations'); ?>"
             href="#">
            <?php echo L::t('Expert Operations'); ?>
          </a>
        </li>
        <li class="<?php echo $expertClass; ?> chosen-dropup">
          <select <?php echo ($_['expertmode'] != 'on' ? 'disabled="disabled"' : '') ?>
            id="app-settings-debugmode"
            multiple
            name="debugmode"
            data-placeholder="<?php echo L::t('Enable Debug Mode'); ?>"
            class="debug-mode chosen-dropup tooltip-<?php echo $sideBarToolTipPos; ?>"
            title="<?php echo Config::toolTips('debug-mode'); ?>">
            <?php
            foreach ($debugModes as $key => $value) {
              echo '<option value="'.$key.'" '.(Config::$debug[$key] ? 'selected="selected"' : '').'>'.$value.'</option>'."\n";
            }
            ?>
          </select>
        </li>
        <li><br/></li>
      </ul>
    </div>
  </div>
</div>
<div id="app-content">
  <form id="personalsettings" class="visible" method="post" action="?app=<?php echo Config::APP_NAME; ?>">
    <input type="hidden" name="requesttoken" value="<?php echo $_['requesttoken']; ?>" />
    <?php echo $navigationControls; ?>
    <div class="buttonseparator"></div>
    <?php echo $settingsControls; ?>
  </form>
  <div id="controls">
<form id="old-personalsettings" class="invisible" method="post" action="?app=<?php echo Config::APP_NAME; ?>">
    <input type="hidden" name="requesttoken" value="<?php echo $_['requesttoken']; ?>" />
    <?php echo $navigationControls; ?>
    <div class="buttonseparator"></div>
    <?php echo $settingsControls; ?>
  </form>
  </div>
<div class="cafevdb-general" data-snap-ignore="true" id="cafevdb-general"> <!-- used to eliminate the pixel-size of the control bar -->
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

<?php } // namespace CAFEVDB ?>
