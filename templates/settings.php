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

namespace CAFEVDB
{

  echo Util::emitExternalScripts();
  echo Util::emitInlineScripts();

  $tooltipstitle  = Config::tooltips('show-tool-tips');
  $filtervistitle = Config::tooltips('filter-visibility');
  $directchgtitle = Config::tooltips('direct-change');
  $pagerowstitle  = Config::tooltips('table-rows-per-page');
  $experttitle    = Config::tooltips('expert-operations');
  $debugtitle     = Config::tooltips('debug-mode');

  $debugModes = array('general' => L::t('General Information'),
                      'query' => L::t('SQL Queries'),
                      'request' => L::t('HTTP Request'),
                      'tooltips' => L::t('Missing Context Help'),
                      'emailform' => L::t('Mass Email Form'));

  $pageRows = floor($_['pagerows'] / 10) * 10;
  $pageRowsOptions = array(-1 => '&infin;');
  $maxRows = 100;
  for ($i = 10; $i <= $maxRows; $i += 10) {
    $pageRowsOptions[$i] = $i;
  }
  if ($pageRows > $maxRows) {
    $pageRows = 0;
  }

  Config::init();
  $timezone = Util::getTimezone();
  $locale = Util::getLocale();
  date_default_timezone_set($timezone);
  $timestamp = strftime('%Y%m%d-%H%M%S');
  $oldlocale = setlocale(LC_TIME, $locale);
  $time = strftime('%x %X');
  setlocale(LC_TIME, $oldlocale);

?>
  <ul id="adminsettingstabs">
    <li><a href="#tabs-1"><?php echo L::t('Personal'); ?></a></li>
    <?php $tabNo = 2; if ($_['adminsettings']) { ?>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo L::t('Orchestra'); ?></a></li>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo L::t('Data-Base'); ?></a></li>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo L::t('Sharing'); ?></a></li>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo L::t('Email'); ?></a></li>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo L::t('Development'); ?></a></li>
      <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo L::t('CMS'); ?></a></li>
    <?php } ?>
    <li><a href="#tabs-<?php echo $tabNo++; ?>"><?php echo L::t('?'); ?></a></li>
  </ul>

  <div id="tabs-1" class="personalblock <?php if ($_['adminsettings']) echo 'admin'; ?>">
    <form id="cafevdb">
      <input id="tooltips" type="checkbox" name="tooltips" <?php echo $_['tooltips'] == 'on' ? 'checked="checked"' : ''; ?> id="tooltips" title="<?php echo Config::tooltips('show-tool-tips'); ?>"/>
      <label for="tooltips" title="<?php echo $tooltipstitle; ?>"><?php echo L::t('Tool-Tips') ?></label>
      <br />
      <input id="filtervisibility" type="checkbox" name="filtervisibility" <?php echo $_['filtervisibility'] == 'on' ? 'checked="checked"' : ''; ?> title="<?php echo $filtervistitle ?>"/>
      <label for="filtervisibility" title="<?php echo $filtervistitle; ?>"><?php echo L::t('Filter-Controls') ?></label>
      <br />
      <input id="directchange" type="checkbox" name="directchange" <?php echo $_['directchange'] == 'on' ? 'checked="checked"' : ''; ?> title="<?php echo $directchgtitle ?>"/>
      <label for="directchange" title="<?php echo $directchgtitle; ?>">
        <?php echo L::t('Quick Change-Dialog') ?>
      </label>
      <br />
      <div class="table-pagerows settings-control">
        <select name="pagerows"
                data-placeholder="<?php echo L::t('#Rows'); ?>"
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
          <?php echo L::t('Display #Rows/Page in Tables'); ?>
        </label>
      </div>
      <div class="wysiwygeditor settings-control">
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
        </select>
      </div>
      <input id="expertmode" type="checkbox" name="expertmode" <?php echo $_['expertmode'] == 'on' ? 'checked="checked"' : ''; ?> id="expertmode" title="<?php echo $experttitle ?>"/>
      <label for="expertmode" title="<?php echo $experttitle; ?>"><?php echo L::t('Expert-Mode') ?></label>
      <br />
      <select <?php echo ($_['expertmode'] != 'on' ? 'disabled="disabled"' : '') ?>
        multiple
        name="debugmode"
        data-placeholder="<?php echo L::t('Enable Debug Mode'); ?>"
        class="debug-mode"
        title="<?php echo $debugtitle; ?>">
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
    <div class="locale information">
      <span class="locale heading"><?php echo L::t('Locale Information:'); ?></span>
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
