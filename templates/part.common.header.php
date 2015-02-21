<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  echo Util::emitExternalScripts();
  echo Util::emitInlineScripts();

  $css_pfx   = $_['css-prefix'];
  $css_class = isset($_['css-class']) ? ' '.$_['css-class'] : '';

  $redoDisabled = $_['historyPosition'] == 0;
  $undoDisabled = $_['historySize'] - $_['historyPosition'] <= 1;

  $navigationControls = Navigation::buttonsFromArray(
    array(
      'undo' => array(
        'name' => L::t('Back'),
        'title' => L::t('Navigate back to the previous view in the recorded history.'),
        'image' => \OCP\Util::imagePath('cafevdb', 'undo.svg'),
        'class' => 'undo navigation history',
        'id' => 'undobutton',
        'disabled' => $undoDisabled,
        'type' => 'submitbutton'),
      'reload' => array(
        'name' => L::t('Reload'),
        'title' => L::t('Reload the current view.'),
        'image' => array(\OCP\Util::imagePath('cafevdb', 'reload.svg'),
                         \OCP\Util::imagePath('core', 'loading.gif')),
        'class' => 'reload navigation history',
        'id' => 'reloadbutton',
        'type' => 'submitbutton'),
      'redo' => array(
        'name' => L::t('Next'),
        'title' => L::t('Navigate to the next view in the recorded history.'),
        'image' => \OCP\Util::imagePath('cafevdb', 'redo.svg'),
        'class' => 'redo navigation history',
        'id' => 'redobutton',
        'disabled' => $redoDisabled,
        'type' => 'submitbutton'),
      'home' => array(
        'name' => L::t('Startpage'),
        'title' => L::t('Navigate back to the start-page.'),
        'image' => \OCP\Util::imagePath('core', 'places/home.svg'),
        'class' => 'settings navigation home',
        'id' => 'homebutton',
        'type' => 'submitbutton')));

  $settingsControls = Navigation::buttonsFromArray(
    array(
      'expert' => array(
        'name' => L::t('Expert Operations'),
        'title' => L::t('Expert Operations like recreating views etc.'),
        'image' => \OCP\Util::imagePath('core', 'actions/rename.svg'),
        'class' => 'settings expert',
        'style' => ($_['expertmode'] != 'on' ? 'display:none' : ''),
        'id' => 'expertbutton'),
      'settings' => array(
        'name' => L::t('Settings'),
        'title' => L::t('Personal Settings.'),
        'image' => \OCP\Util::imagePath('core', 'actions/settings.svg'),
        'class' => 'settings generalsettings',
        'id' => 'settingsbutton'),
      'tooltips' => array(
        'name' => L::t('Tooltip Button'),
        'title' => L::t('Toggle Tooltips'),
        'image' => \OCP\Util::imagePath('core', 'actions/info.svg'),
        'class' => 'help tooltips tipsy-ne tooltips-'.($_['tooltips'] == 'on' ? 'en' : 'dis').'abled',
        'id' => 'tooltipbutton')
      ));

  if (!isset($_['headerblock']) && isset($_['header'])) {
    $header = $_['header']; 
  } else {
    $header = '';
  }

?>
<div id="controls">
<?php echo $_['navigationcontrols']; ?>
  <form id="personalsettings" method="post" action="?app=<?php echo Config::APP_NAME; ?>">
    <input type="hidden" name="requesttoken" value="<?php echo \OCP\Util::callRegister(); ?>" />
    <?php echo $navigationControls; ?>
    <div class="buttonseparator"></div>
    <?php echo $settingsControls; ?>
  </form>
</div>
<div class="cafevdb-general" id="cafevdb-general"> <!-- used to eliminate the pixel-size of the control bar -->
  <?php echo isset($_['headerblock']) ? '<!-- ' : ''; ?>
  <div id="<?php echo $css_pfx; ?>-header-box" class="<?php echo $css_pfx; ?>-header-box<?php echo $css_class; ?>">
    <div id="<?php echo $css_pfx; ?>-header" class="<?php echo $css_pfx; ?>-header<?php echo $css_class; ?>">
      <?php echo $header; ?>
    </div>
  </div>
  <?php echo isset($_['headerblock']) ? ' -->' : ''; ?>
  <?php echo isset($_['headerblock']) ? $_['headerblock'] : ''; ?>
  <div id="<?php echo $css_pfx; ?>-container" class="<?php echo $css_pfx; ?>-container<?php echo $css_class; ?>"> <!-- used to have something with 100 height for scrollbars -->
    <div id="<?php echo $css_pfx; ?>-body" class="<?php echo $css_pfx; ?>-body<?php echo $css_class; ?>">

<?php } // namespace CAFEVDB ?>
