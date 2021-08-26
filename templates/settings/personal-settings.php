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

?>

<div id="tabs-<?php echo $_['tabNr']; ?>" class="personalblock <?php if ($_['adminsettings']) echo 'admin'; ?>">
  <form id="cafevdb" class="personal-settings">
    <input id="tooltips"
           type="checkbox"
           class="checkbox tooltips <?php p($toolTipClass); ?>"
           name="tooltips" <?php echo $showToolTips == 'on' ? 'checked="checked"' : ''; ?>
           title="<?php echo $toolTips['show-tool-tips']; ?>"
    />
    <label for="tooltips" class="<?php p($toolTipClass); ?>" title="<?php echo $tooltipstitle; ?>">
      <?php echo $l->t('Tool-Tips') ?>
    </label>
    <br />
    <input id="filtervisibility"
           type="checkbox"
           class="checkbox filtervisibility <?php p($toolTipClass); ?>"
           name="filtervisibility" <?php echo $_['filtervisibility'] == 'on' ? 'checked="checked"' : ''; ?>
           title="<?php echo $filtervistitle ?>"
    />
    <label for="filtervisibility"
           class="<?php p($toolTipClass); ?>"
           title="<?php echo $filtervistitle; ?>">
      <?php echo $l->t('Filter-Controls') ?>
    </label>
    <br />
    <input id="directchange"
           type="checkbox"
           class="checkbox directchange <?php p($toolTipClass); ?>"
           name="directchange" <?php echo $_['directchange'] == 'on' ? 'checked="checked"' : ''; ?>
           title="<?php echo $directchgtitle ?>"
    />
    <label for="directchange"
           class="<?php p($toolTipClass); ?>"
           title="<?php echo $directchgtitle; ?>">
      <?php echo $l->t('Quick Change-Dialog') ?>
    </label>
    <br />
    <div class="table-pagerows settings-control">
      <select name="pagerows"
              data-placeholder="<?php echo $l->t('#Rows'); ?>"
              class="table-pagerows pagerows <?php p($toolTipClass); ?>"
              id="table-pagerows"
              title="<?php echo $pagerowstitle; ?>">
        <?php
        foreach($pageRowsOptions as $value => $text) {
          $selected = $value == $pageRows ? ' selected="selected"' : '';
          echo '<option value="'.$value.'"'.$selected.'>'.$text.'</option>'."\n";
        }
        ?>
      </select>
      <label for="table-pagerows"
             class="<?php p($toolTipClass); ?>"
             title="<?php echo $pagerowstitle; ?>">
        <?php echo $l->t('Display #Rows/Page in Tables'); ?>
      </label>
    </div>
    <div class="wysiwyg-editor settings-control">
      <select name="wysiwygEditor"
              data-placeholder="<?php echo $l->t('WYSIWYG Editor'); ?>"
              class="wysiwyg-editor <?php p($toolTipClass); ?>"
              id="wysiwyg-editor"
              title="<?php echo $toolTips['wysiwyg-editor']; ?>">
        <?php
        foreach ($wysiwygOptions as $key => $value) {
          $disabled = $value['enabled'] ? '' : ' disabled ';
          echo '<option value="'.$key.'" '.$disabled.($wysiwygEditor == $key ? 'selected="selected"' : '').'>'.$value['name'].'</option>'."\n";
        }
        ?>
      </select>
      <label for="wysiwyg-editor"
             class="<?php p($toolTipClass); ?>"
             title="<?php echo $wysiwygtitle; ?>">
        <?php echo $l->t('WYSIWYG Text-Editor'); ?>
      </label>
    </div>
    <input id="expertmode"
           type="checkbox"
           class="checkbox expertmode <?php p($toolTipClass); ?>"
           name="expertmode" <?php echo $expertMode == 'on' ? 'checked="checked"' : ''; ?>
           id="expertmode" title="<?php echo $experttitle ?>"
    />
    <label for="expertmode"
           class="<?php p($toolTipClass); ?>"
           title="<?php echo $experttitle; ?>">
      <?php echo $l->t('Expert-Mode') ?>
    </label>
    <br />
    <div class="expertmode-container<?php p($expertClass); ?>">
      <input id="showdisabled"
             type="checkbox"
             class="checkbox showdisabled <?php p($toolTipClass); ?>"
             name="showdisabled" <?php echo $_['showdisabled'] == 'on' ? 'checked="checked"' : ''; ?>
             title="<?php echo $showdistitle ?>"
      />
      <label for="showdisabled"
             class="<?php p($toolTipClass); ?>"
             title="<?php echo $showdistitle; ?>">
        <?php echo $l->t('Show Disabled Data-Sets'); ?>
      </label>
    </div>
    <div class="debugmode-container expertmode-container<?php p($expertClass); ?>">
      <?php echo $this->inc('settings/part.debug-mode', [ 'toolTipsPos' => $toolTipClass ]); ?>
    </div>
    <span class="statusmessage" id="msg"></span><span>&nbsp;</span>
    <input type="text" style="display:none;width:0%;float: left;" name="dummy" id="dummy" value="dummy" placeholder="dummy" title="<?php echo $l->t('Dummy'); ?>" />
  </form>
  <br />
  <form id="userkey">
    <input class="cafevdb-password"
           type="password"
           autocomplete="current-password"
           required="required"
           id="password"
           name="password"
           placeholder="<?php echo $l->t('Own Password');?>" data-typetoggle="#password-show"
    />
    <input class="cafevdb-password-show" type="checkbox" id="password-show" name="password-show" />
    <label class="cafevdb-password-show" for="password-show"><?php echo $l->t('show');?></label>
    <input class="cafevdb-password"
           type="password"
           id="encryptionkey"
           name="encryptionkey"
           value="<?php echo (true ? '' : $_['encryptionkey']); ?>"
           placeholder="<?php echo $l->t('DB Encryption Key');?>"
           data-typetoggle="#userkey-show" />
    <input class="cafevdb-password-show" type="checkbox" id="userkey-show" name="userkey-show" />
    <label class="cafevdb-password-show" for="userkey-show"><?php echo $l->t('show');?></label>
    <input id="button" type="button" value="<?php echo $l->t('Set Encryption Key');?>" />
    <div class="statusmessage changed"><?php echo $l->t('The encryption key has been set successfully.');?></div>
    <div class="statusmessage error"><?php echo $l->t('Unable to set the encryption key.');?></div>
    <div class="statusmessage info"></div>
  </form>
  <div class="locale information">
    <span class="locale heading"><?php echo $l->t('Locale Information:'); ?></span>
    <span class="locale timestamp"><?php echo $timestamp; ?></span>
    <span class="locale time"><?php echo $time; ?></span>
    <span class="locale timezone"><?php echo $timezone; ?></span>
    <span class="locale thelocale"><?php echo $locale; ?></span>
  </div>
</div>
