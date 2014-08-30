<?php
/**Orchestra member, musician and project management application.
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

use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Config;

echo Util::emitExternalScripts();
echo Util::emitInlineScripts();

$tooltipstitle  = L::t("Control the display of tooltips. ".
                      "Warning: this works globally for all OwnCloud applications.");
$headervistitle = L::t("Start with visible page header-texts. This affects only ".
                       "the initial visibility of the page-headers.");
$filtervistitle = L::t("Initially display the filter-controls on all atable. This affects only ".
                       "the initial visibility of the filter-buttons and -inputs.");
$experttitle    = L::t("Show a second button which leads to a dialog with `advanced' settings");
$debugtitle     = L::t("Show a certain amount of debug information, normally not needed.");

$debugModes = array('general' => L::t('General Information'),
                    'query' => L::t('SQL Queries'),
                    'request' => L::t('HTTP Request'));

?>
<?php if ($_['adminsettings']) { ?>
<ul id="adminsettingstabs">
  <li><a href="#tabs-1"><?php echo L::t('Personal'); ?></a></li>
  <li><a href="#tabs-2"><?php echo L::t('Orchestra'); ?></a></li>
  <li><a href="#tabs-3"><?php echo L::t('Data-Base'); ?></a></li>
  <li><a href="#tabs-4"><?php echo L::t('Sharing'); ?></a></li>
  <li><a href="#tabs-5"><?php echo L::t('Email'); ?></a></li>
  <li><a href="#tabs-6"><?php echo L::t('Development'); ?></a></li>
</ul>
<?php } ?>

<div id="tabs-1" class="personalblock <?php if ($_['adminsettings']) echo 'admin'; ?>">
  <form id="cafevdb">
    <input id="tooltips" type="checkbox" name="tooltips" <?php echo $_['tooltips'] == 'on' ? 'checked="checked"' : ''; ?> id="tooltips" title="<?php echo $tooltipstitle ?>"/>
    <label for="tooltips" title="<?php echo $tooltipstitle; ?>"><?php echo L::t('Tool-Tips') ?></label>
    <br />
    <input id="headervisibility" type="checkbox" name="headervisibility" <?php echo $_['headervisibility'] == 'expanded' ? 'checked="checked"' : ''; ?> id="tooltips" title="<?php echo $headervistitle ?>"/>
    <label for="headervisibility" title="<?php echo $headervistitle; ?>"><?php echo L::t('Page-Header') ?></label>
    <br />
    <input id="filtervisibility" type="checkbox" name="filtervisibility" <?php echo $_['filtervisibility'] == 'on' ? 'checked="checked"' : ''; ?> id="tooltips" title="<?php echo $headervistitle ?>"/>
    <label for="filtervisibility" title="<?php echo $filtervistitle; ?>"><?php echo L::t('Filter-Controls') ?></label>
    <br />
    <select name="wysiwygEditor"
            data-placeholder="<?php echo L::t('WYSIWYG Editor'); ?>"
            class="wysiwyg-editor"
            title="<?php echo Config::toolTips('wysiwyg-edtior'); ?>">
<?php
foreach (Config::$wysiwygEditors as $key => $value) {
  $disabled = $value['enabled'] ? '' : ' disabled="disabled" ';
  echo '<option value="'.$key.'" '.$disabled.($_['editor'] == $key ? 'selected="selected"' : '').'>'.$value['name'].'</option>'."\n";
}
?>
    </select></br>
    <input id="expertmode" type="checkbox" name="expertmode" <?php echo $_['expertmode'] == 'on' ? 'checked="checked"' : ''; ?> id="expertmode" title="<?php echo $experttitle ?>"/>
    <label for="expertmode" title="<?php echo $experttitle; ?>"><?php echo L::t('Expert-Mode') ?></label>
    <br />
    <select <?php echo ($_['expertmode'] != 'on' ? 'disabled="disabled"' : '') ?>
            multiple
            name="debugmode"
            data-placeholder="<?php echo L::t('Enable Debug Mode'); ?>"
            class="debug-mode"
            title="title=<?php echo $debugtitle; ?>">
<?php
foreach ($debugModes as $key => $value) {
  echo '<option value="'.$key.'" '.(Config::$debug[$key] ? 'selected="selected"' : '').'>'.$value.'</option>'."\n";
}
?>
    </select>
    <br />
    <input type="text" style="display:none;width:0%;float: left;" name="dummy" id="dummy" value="dummy" placeholder="dummy" title="<?php echo L::t('Dummy'); ?>" />
    <span class="statusmessage" id="msg"></span>
  </form>
  <form id="userkey">
    <input class="cafevdb-password" type="password" id="password" name="password" placeholder="<?php echo L::t('Own Password');?>" data-typetoggle="#password-show" />
    <input class="cafevdb-password-show" type="checkbox" id="password-show" name="password-show" />
    <label class="cafevdb-password-show" for="password-show"><?php echo L::t('show');?></label>
    <input class="cafevdb-password" type="password" id="encryptionkey" name="encryptionkey" value="<?php echo (true ? '' : $_['encryptionkey']); ?>" placeholder="<?php echo L::t('DB Encryption Key');?>" data-typetoggle="#userkey-show" />
    <input class="cafevdb-password-show" type="checkbox" id="userkey-show" name="userkey-show" />
    <label class="cafevdb-password-show" for="userkey-show"><?php echo L::t('show');?></label>
    <input id="button" type="button" value="<?php echo L::t('Set Encryption Key');?>" />
    <div class="statusmessage" id="changed"><?php echo L::t('The encryption key has been set successfully.');?></div>
    <div class="statusmessage" id="error"><?php echo L::t('Unable to set the encryption key.');?></div>
  </form>
</div>
<?php
  $tabNo = 2;
  if ($_['adminsettings']) {
    echo $this->inc("orchestra-settings", array('tabNr' => $tabNo++));
    echo $this->inc("app-settings", array('tabNr' => $tabNo++));
    echo $this->inc("share-settings", array('tabNr' => $tabNo++));
    echo $this->inc("email-settings", array('tabNr' => $tabNo++));
    echo $this->inc("devel-settings", array('tabNr' => $tabNo++));
  }
?>
