<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024, 2025 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;

$deselectInvisibleMiscRecs = $_[EnumPersonalSettingsKey::DESELECT_INVISIBLE_MISC_RECS->value];
$diretChange = $_[EnumPersonalSettingsKey::DIRECT_CHANGE->value];
$encryptionKey = $_[EnumPersonalSettingsKey::ENCRYPTION_KEY->value];
$expertMode = $_[EnumPersonalSettingsKey::EXPERT_MODE->value];
$financeMode = $_[EnumPersonalSettingsKey::FINANCE_MODE->value];
$initialFilterVisibility = $_[EnumPersonalSettingsKey::INITIAL_FILTER_VISIBILITY->value];
$restoreHistory = $_[EnumPersonalSettingsKey::RESTORE_HISTORY->value];
$showDisabled = $_[EnumPersonalSettingsKey::SHOW_DISABLED->value];
$toolTipsEnabled = $_[EnumPersonalSettingsKey::TOOL_TIPS_ENABLED->value];
$wysiwygEditor = $_[EnumPersonalSettingsKey::WYSIWYG_EDITOR->value];

?>

<div id="tabs-<?= $_['tabNr']; ?>" class="personalblock <?php $_['adminsettings'] && p('admin'); ?>">
  <form id="app-cafevdb" class="personal-settings">
    <input id="tooltips"
           type="checkbox"
           class="checkbox tooltips <?php p($toolTipClass); ?>"
           name="tooltips"<?php ($toolTipsEnabled == 'on') && print(' checked="checked"') ?>
    />
    <label for="tooltips" class="<?php p($toolTipClass); ?>" title="<?= $tooltipstitle; ?>">
      <?= $l->t('Tool-Tips') ?>
    </label>
    <br />
    <input id="restorehistory"
           type="checkbox"
           class="checkbox restorehistory <?php p($toolTipClass); ?>"
           name="restorehistory"<?php ($restoreHistory == 'on') && print(' checked="checked"') ?>
    />
    <label for="restorehistory"
           class="<?php p($toolTipClass); ?>"
           title="<?= $restorehistorytitle; ?>">
      <?= $l->t('Restore Last View') ?>
    </label>
    <br />
    <input id="filtervisibility"
           type="checkbox"
           class="checkbox filtervisibility <?php p($toolTipClass); ?>"
           name="filtervisibility"<?php ($initialFilterVisibility == 'on') && print(' checked="checked"') ?>
    />
    <label for="filtervisibility"
           class="<?php p($toolTipClass); ?>"
           title="<?= $filtervistitle; ?>">
      <?= $l->t('Filter-Controls') ?>
    </label>
    <br />
    <input id="directchange"
           type="checkbox"
           class="checkbox directchange <?php p($toolTipClass); ?>"
           name="directchange"<?php ($directChange == 'on') && print(' checked="checked"') ?>
    />
    <label for="directchange"
           class="<?php p($toolTipClass); ?>"
           title="<?= $directchgtitle; ?>">
      <?= $l->t('Quick Change-Dialog') ?>
    </label>
    <br />
    <input id="deselect-invisible-misc-recs"
           type="checkbox"
           class="checkbox deselect-invisible-misc-recs <?php p($toolTipClass); ?>"
           name="deselect_invisible_misc_recs"<?php ($deselectInvisibleMiscRecs == 'on') && print(' checked="checked"') ?>
    />
    <label for="deselect-invisible-misc-recs"
           class="<?php p($toolTipClass); ?>"
           title="<?= $toolTips['deselect-invisible-misc-recs']; ?>">
      <?= $l->t('Deselect Invisible') ?>
    </label>
    <br />
    <div class="table-pagerows settings-control">
      <select name="pagerows"
              data-placeholder="<?= $l->t('#Rows'); ?>"
              class="table-pagerows pagerows <?php p($toolTipClass); ?>"
              id="table-pagerows"
        <?php
        foreach ($pageRowsOptions as $value => $text) {
          $selected = $value == $pageRows ? ' selected="selected"' : '';
          echo '<option value="'.$value.'"'.$selected.'>'.$text.'</option>'."\n";
        }
        ?>
      </select>
      <label for="table-pagerows"
             class="<?php p($toolTipClass); ?>"
             title="<?= $pagerowstitle; ?>">
        <?= $l->t('Display #Rows/Page in Tables'); ?>
      </label>
    </div>
    <div class="wysiwyg-editor settings-control">
      <select name="wysiwygEditor"
              data-placeholder="<?= $l->t('WYSIWYG Editor'); ?>"
              class="wysiwyg-editor <?php p($toolTipClass); ?>"
              id="wysiwyg-editor"
              title="<?= $toolTips['wysiwyg-editor']; ?>"
      >
        <?php
        foreach ($wysiwygOptions as $key => $value) {
          $disabled = $value['enabled'] ? '' : 'disabled ';
          echo '<option value="'.$key.'" ' . $disabled . ($wysiwygEditor == $key ? 'selected="selected"' : '').'>'.$value['name'].'</option>'."\n";
        }
        ?>
      </select>
      <label for="wysiwyg-editor"
             class="<?php p($toolTipClass); ?>"
             title="<?= $wysiwygtitle; ?>">
        <?= $l->t('WYSIWYG Text-Editor'); ?>
      </label>
    </div>
    <?php if ($roles->inTreasurerGroup()) { ?>
    <input id="finance-mode"
           type="checkbox"
           class="checkbox finance-mode <?php p($toolTipClass); ?>"
           name="financeMode"<?php ($financeMode == 'on') && print(' checked="checked"') ?>
    />
    <label for="finance-mode"
           class="<?php p($toolTipClass); ?>"
           title="<?= $financetitle; ?>">
      <?= $l->t('Finance-Mode') ?>
    </label>
    <br />
    <?php } ?>
    <input id="expert-mode"
           type="checkbox"
           class="checkbox expert-mode <?php p($toolTipClass); ?>"
           name="expertMode"<?php ($expertMode == 'on') && print(' checked="checked"') ?>
    />
    <label for="expert-mode"
           class="<?php p($toolTipClass); ?>"
           title="<?= $experttitle; ?>">
      <?= $l->t('Expert-Mode') ?>
    </label>
    <br />
    <div class="expert-mode-container<?php p($expertClass); ?>">
      <input id="showdisabled"
             type="checkbox"
             class="checkbox showdisabled <?php p($toolTipClass); ?>"
             name="showdisabled" <?php ($showDisabled == 'on') && print('checked="checked"') ?>
      />
      <label for="showdisabled"
             class="<?php p($toolTipClass); ?>"
             title="<?= $showdistitle; ?>">
        <?= $l->t('Show Disabled Data-Sets'); ?>
      </label>
    </div>
    <div class="debugmode-container expert-mode-container<?php p($expertClass); ?>">
      <?= $this->inc('settings/part.debug-mode', [ 'toolTipsPos' => $toolTipsPos ]); ?>
    </div>
    <span class="statusmessage" id="msg"></span><span>&nbsp;</span>
    <input type="text" style="display:none;width:0%;float: left;" name="dummy" id="dummy" value="dummy" placeholder="dummy" title="<?= $l->t('Dummy'); ?>" />
  </form>
  <br />
  <form id="userkey" class="flex-container">
    <fieldset class="flex-container">
      <input type="hidden"
             name="userId"
             value="<?php p($userId); ?>"
             autocomplete="username"
      />
      <div class="password-container">
        <input class="cafevdb-password tooltip-auto"
               type="password"
               autocomplete="current-password"
               required="required"
               id="password"
               name="password"
               title="<?php p($toolTips["settings:personal:{$encryptionKey}:own-password"]); ?>"
               placeholder="<?= $l->t('Own Password');?>" data-typetoggle="#password-show"
        />
        <input class="cafevdb-password-show" type="checkbox" id="password-show" name="password-show" />
        <label class="cafevdb-password-show" for="password-show"><?= $l->t('show');?></label>
      </div>
    </fieldset>
    <fieldset class="flex-container password-container">
      <input type="hidden"
             name="orchestraUserGroup"
             value="@<?php p($orchestraUserGroup); ?>"
             autocomplete="username"
      />
      <div class="password-container">
        <input class="cafevdb-password tooltip-auto"
               type="password"
               id="<?= $encryptionKey ?>"
               name="<?= $encryptionKey ?>"
               value="<?= (true ? '' : $encryptionKey); ?>"
               placeholder="<?= $l->t('DB Encryption Key');?>"
               title="<?= $toolTips["settings:personal:{$encryptionKey}"] ?>"
               data-typetoggle="#userkey-show" />
        <input class="cafevdb-password-show" type="checkbox" id="userkey-show" name="userkey-show" />
        <label class="cafevdb-password-show" for="userkey-show"><?= $l->t('show');?></label>
      </div>
      <input id="<?= $encryptionKey ?>-button"
             class="button"
             type="button"
             value="<?= $l->t('Set Encryption Key');?>"
      />
    </fieldset>
    <div class="statusmessage changed"><?= $l->t('The encryption key has been set successfully.');?></div>
    <div class="statusmessage error"><?= $l->t('Unable to set the encryption key.');?></div>
    <div class="statusmessage info"></div>
  </form>
  <?= $this->inc('settings/part.locale-info', [ 'l10n' => $l ]); ?>
</div>
