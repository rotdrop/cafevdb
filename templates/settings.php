<?php
/* Orchestra member, musician and project management application.
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

style($appName, 'settings');

$tooltipstitle  = $toolTipsData['show-tool-tips'];
$filtervistitle = $toolTipsData['filter-visibility'];
$directchgtitle = $toolTipsData['direct-change'];
$showdistitle   = $toolTipsData['show-disabled'];
$pagerowstitle  = $toolTipsData['table-rows-per-page'];
$experttitle    = $toolTipsData['expert-operations'];
$debugtitle     = $toolTipsData['debug-mode'];

$debugModes = array(ConfigService::DEBUG_GENERAL => $l->t('General Information'),
                    ConfigService::DEBUG_QUERY => $l->t('SQL Queries'),
                    ConfigService::DEBUG_REQUEST => $l->t('HTTP Request'),
                    ConfigService::DEBUG_TOOLTIPS => $l->t('Missing Context Help'),
                    ConfigService::DEBUG_EMAILFORM => $l->t('Mass Email Form'));

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

?>
  <ul id="adminsettingstabs">
    <li><a href="#tabs-1"><?php echo $l->t('Personal'); ?></a></li>
    <?php $tabNo = 2; if ($_['adminsettings']) { ?>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo $l->t('Orchestra'); ?></a></li>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo $l->t('Data-Base'); ?></a></li>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo $l->t('Sharing'); ?></a></li>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo $l->t('Email'); ?></a></li>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo $l->t('Development'); ?></a></li>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo $l->t('CMS'); ?></a></li>
    <?php } ?>
    <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo $l->t('?'); ?></a></li>
  </ul>

  <div id="tabs-1" class="personalblock <?php if ($_['adminsettings']) echo 'admin'; ?>">
    <form id="cafevdb">
      <input id="tooltips"
             type="checkbox"
             class="checkbox"
             name="tooltips" <?php echo $_['tooltips'] == 'on' ? 'checked="checked"' : ''; ?>
             id="tooltips"
             title="<?php echo $toolTipsData['show-tool-tips']; ?>"
             />
      <label for="tooltips" title="<?php echo $tooltipstitle; ?>">
        <?php echo $l->t('Tool-Tips') ?>
      </label>
      <br />
      <input id="filtervisibility"
             type="checkbox"
             class="checkbox"
             name="filtervisibility" <?php echo $_['filtervisibility'] == 'on' ? 'checked="checked"' : ''; ?>
             title="<?php echo $filtervistitle ?>"
             />
      <label for="filtervisibility" title="<?php echo $filtervistitle; ?>">
        <?php echo $l->t('Filter-Controls') ?>
      </label>
      <br />
      <input id="directchange"
             type="checkbox"
             class="checkbox"
             name="directchange" <?php echo $_['directchange'] == 'on' ? 'checked="checked"' : ''; ?>
             title="<?php echo $directchgtitle ?>"
             />
      <label for="directchange" title="<?php echo $directchgtitle; ?>">
        <?php echo $l->t('Quick Change-Dialog') ?>
      </label>
      <br />
      <input id="showdisabled"
             type="checkbox"
             class="checkbox"
             name="showdisabled" <?php echo $_['showdisabled'] == 'on' ? 'checked="checked"' : ''; ?>
             title="<?php echo $showdistitle ?>"
             />
      <label for="showdisabled" title="<?php echo $showdistitle; ?>">
        <?php echo $l->t('Show Disabled Data-Sets'); ?>
      </label>
      <br />
      <div class="table-pagerows settings-control">
        <select name="pagerows"
                data-placeholder="<?php echo $l->t('#Rows'); ?>"
                class="table-pagerows"
                id="table-pagerows"
                title="<?php echo $pagerowstitle; ?>">
          <?php
          foreach($pageRowsOptions as $value => $text) {
            $selected = $value == $pageRows ? ' selected="selected"' : '';
            echo '<option value="'.$value.'"'.$selected.'>'.$text.'</option>'."\n";
          }
          ?>
        </select>
        <label for="table-pagerows" title="<?php echo $pagerowstitle; ?>">
          <?php echo $l->t('Display #Rows/Page in Tables'); ?>
        </label>
      </div>
      <div class="wysiwygeditor settings-control">
        <select name="wysiwygEditor"
                data-placeholder="<?php echo $l->t('WYSIWYG Editor'); ?>"
                class="wysiwyg-editor"
                title="<?php echo $toolTipsData['wysiwyg-edtior']; ?>">
          <?php
          foreach ($wysiwygOptions as $key => $value) {
            $disabled = $value['enabled'] ? '' : ' disabled="disabled" ';
            echo '<option value="'.$key.'" '.$disabled.($_['editor'] == $key ? 'selected="selected"' : '').'>'.$value['name'].'</option>'."\n";
          }
          ?>
        </select>
      </div>
      <input id="expertmode"
             type="checkbox"
             class="checkbox"
             name="expertmode" <?php echo $_['expertmode'] == 'on' ? 'checked="checked"' : ''; ?>
             id="expertmode" title="<?php echo $experttitle ?>"
             />
      <label for="expertmode" title="<?php echo $experttitle; ?>">
        <?php echo $l->t('Expert-Mode') ?>
      </label>
      <br />
      <select <?php echo ($_['expertmode'] != 'on' ? 'disabled="disabled"' : '') ?>
        multiple
        name="debugmode"
        data-placeholder="<?php echo $l->t('Enable Debug Mode'); ?>"
        class="debug-mode"
        title="<?php echo $debugtitle; ?>">
        <?php
        foreach ($debugModes as $key => $value) {
          echo '<option value="'.$key.'" '.(($debugMode & $key) != 0 ? 'selected="selected"' : '').'>'.$value.'</option>'."\n";
        }
        ?>
      </select>
      <br />
      <input type="text" style="display:none;width:0%;float: left;" name="dummy" id="dummy" value="dummy" placeholder="dummy" title="<?php echo $l->t('Dummy'); ?>" />
      <span class="statusmessage" id="msg"></span>
    </form>
    <form id="userkey">
      <input class="cafevdb-password" type="password" id="password" name="password" placeholder="<?php echo $l->t('Own Password');?>" data-typetoggle="#password-show" />
      <input class="cafevdb-password-show" type="checkbox" id="password-show" name="password-show" />
      <label class="cafevdb-password-show" for="password-show"><?php echo $l->t('show');?></label>
      <input class="cafevdb-password" type="password" id="encryptionkey" name="encryptionkey" value="<?php echo (true ? '' : $_['encryptionkey']); ?>" placeholder="<?php echo $l->t('DB Encryption Key');?>" data-typetoggle="#userkey-show" />
      <input class="cafevdb-password-show" type="checkbox" id="userkey-show" name="userkey-show" />
      <label class="cafevdb-password-show" for="userkey-show"><?php echo $l->t('show');?></label>
      <input id="button" type="button" value="<?php echo $l->t('Set Encryption Key');?>" />
      <div class="statusmessage" id="changed"><?php echo $l->t('The encryption key has been set successfully.');?></div>
      <div class="statusmessage" id="error"><?php echo $l->t('Unable to set the encryption key.');?></div>
    </form>
    <div class="locale information">
      <span class="locale heading"><?php echo $l->t('Locale Information:'); ?></span>
      <span class="locale timestamp"><?php echo $timestamp; ?></span>
      <span class="locale time"><?php echo $time; ?></span>
      <span class="locale timezone"><?php echo $timezone; ?></span>
      <span class="locale thelocale"><?php echo $locale; ?></span>
    </div>
  </div>
<?php
  $tabNo = 2;
  if ($_['adminsettings']) {
    echo $this->inc("orchestra-settings", array('tabNr' => $tabNo++));
    echo $this->inc("app-settings", array('tabNr' => $tabNo++));
    echo $this->inc("share-settings", array('tabNr' => $tabNo++));
    echo $this->inc("email-settings", array('tabNr' => $tabNo++));
    echo $this->inc("devel-settings", array('tabNr' => $tabNo++));
    echo $this->inc("cms-settings", array('tabNr' => $tabNo++));
  }
  echo $this->inc("about", array('tabNr' => $tabNo++));

} // namespace CAFEVDB
?>
