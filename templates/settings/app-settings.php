<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2020, 2021, 2022, 2023, 2024, 2025 Claus-Justus Heine
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
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Settings\ConfigConstants;

$off = $_['orchestra'] == '' ? 'disabled' : '';

list($appLocale,) = explode('.', $appLocale, 2);
$displayLocale = $localeSymbol;
$localeOptions = [];
foreach ($locales as $localeInfo) {
  $code = $localeInfo['code'];
  $regionCode = locale_get_region($code);
  if (empty($regionCode)) {
    continue;
  }
  $name = locale_get_display_name($code, $displayLocale);
  $localeOptions[] = [
    'value' => $code,
    'name' => $name,
    'flags' => ($code === $appLocale) ? PageNavigation::SELECTED : 0,
  ];
}
usort($localeOptions, fn($a, $b) => strcmp($a['name'], $b['name']));

?>
<div id="tabs-<?= $_['tabNr']; ?>" class="personalblock admin">
  <!-- GENERAL CONFIGURATION STUFF -->
  <form id="admingeneral">
    <fieldset>
      <legend><?= $l->t('General settings'); ?></legend>
      <input type="text"
             id="orchestra"
             name="orchestra"
             value="<?= $_['orchestra']; ?>"
             required="required"
             title="<?= $toolTips['settings:personal:general:orchestra:name']; ?>"
             placeholder="<?= $l->t('name of orchestra'); ?>" />
      <label for="orchestra"><?php p($l->t('name of orchestra')); ?></label>
      <br />
      <select name="<?= ConfigConstants::ORCHESTRA_LOCALE_KEY ?>"
              id="<?= ConfigConstants::ORCHESTRA_LOCALE_KEY ?>"
              title="<?= $toolTips['settings:personal:general:orchestra:locale']; ?>"
      >
        <?= PageNavigation::selectOptions($localeOptions); ?>
      </select>
      <label for="<?= ConfigConstants::ORCHESTRA_LOCALE_KEY ?>"><?php p($l->t('locale of the orchestra')); ?></label>
      <?= $this->inc('settings/part.locale-info', [
        'infoLocaleSymbol' => $appLocale,
        'infoL10n' => $appL,
      ]); ?>
    </fieldset>
  </form>
  <!-- ENCRYPTION-KEY -->
  <form id="systemkey">
    <fieldset class="systemkey flex-container" <?= $off; ?> >
      <legend><?= $l->t('Encryption settings'); ?></legend>
      <div class="password-container">
        <input type="hidden"
               autocomplete="username"
               name="orchestraUserGroup"
               value="@<?php p($orchestraUserGroup); ?>"
        />
        <input class="<?= $appName ?>-password"
               type="password"
               value="<?php false ? p(${EnumPersonalSettingsKey::ENCRYPTION_KEY->value}) : ''; ?>"
               id="oldkey"
               name="oldkey"
               placeholder="<?= $l->t('Current Key');?>"
               data-typetoggle="#oldkey-show"
               autocomplete="current-password"
        />
        <input class="<?= $appName ?>-password-show" type="checkbox" id="oldkey-show" name="show" />
        <label class="<?= $appName ?>-password-show" for="oldkey-show"><?= $l->t('show');?></label>
      </div>
      <div class="password-container">
        <input class="<?= $appName ?>-password randomkey"
               type="password"
               id="key"
               name="systemkey"
               placeholder="<?= $l->t('New Key');?>"
               data-typetoggle="#systemkey-show"
               autocomplete="new-password"
        />
        <input class="<?= $appName ?>-password-show" type="checkbox" id="systemkey-show" name="show" />
        <label class="<?= $appName ?>-password-show" for="systemkey-show"><?= $l->t('show');?></label>
      </div>
      <input name="keygenerate" id="keygenerate" type="button" value="<?= $l->t('Generate'); ?>" title="<?= $l->t('Generate a random encryption key');?>" />
      <input id="keychangebutton" type="button" value="<?= $l->t('Change Encryption Key');?>" />
      <!-- <span><?php p(${EnumPersonalSettingsKey::ENCRYPTION_KEY->value}); ?></span> -->
      <div class="statusmessage changed"><?= $l->t('The encryption key was changed');?></div>
      <div class="statusmessage error"><?= $l->t('Unable to change the encryption key');?></div>
      <div class="statusmessage insecure"><?= $l->t('Data will be stored unencrypted');?></div>
      <div class="statusmessage equal"><?= $l->t('The keys are the same and remain unchanged.');?></div>
      <div class="statusmessage standby"><?= $l->t('Please standby, this action needs some seconds.');?></div>
      <div class="statusmessage general"></div>
    </fieldset>
    <!-- DISTRIBUTE ENCRYPTION-KEY -->
    <fieldset class="keydistribute" <?= $off; ?> >
      <input id="keydistributebutton"
             type="button"
             name="keydistribute"
             value="<?= $l->t('Distribute Encryption Key');?>"
             title="<?= $l->t(
                    'Insert the data-base encryption key into the user preferences of all users belonging to the user group.'
                    . ' The data-base key will be encrypted by the respective user\'s public key.') ?>"
      />
      <span class="statusmessage"></span>
    </fieldset>
  </form>
  <!-- GENERAL DATA-BASE STUFF -->
  <form id="dbsettings">
    <fieldset id="dbgeneral"  <?= $off; ?> ><legend><?= $l->t('Database settings'); ?></legend>
      <input type="text"
             autocomplete="on"
             name="<?= ConfigConstants::APP_DB_SERVER ?>"
             id="<?= ConfigConstants::APP_DB_SERVER ?>"
             value="<?= $_[ConfigConstants::APP_DB_SERVER]; ?>"
             placeholder="<?= $l->t('Server');?>"
      />
      <label for="<?= ConfigConstants::APP_DB_SERVER ?>"><?= $l->t('Database Server');?></label>
      <br/>
      <input type="text"
             autocomplete="on"
             name="<?= ConfigConstants::APP_DB_NAME ?>"
             id="<?= ConfigConstants::APP_DB_NAME ?>"
             value="<?= $_[ConfigConstants::APP_DB_NAME]; ?>"
             placeholder="<?= $l->t('Database Name');?>"
      />
      <label for="<?= ConfigConstants::APP_DB_NAME ?>"><?= $l->t('Database Name');?></label>
      <br/>
      <input type="text"
             name="<?= ConfigConstants::APP_DB_USER ?>"
             id="<?= ConfigConstants::APP_DB_USER ?>"
             value="<?= $_[ConfigConstants::APP_DB_USER]; ?>"
             placeholder="<?= $l->t('User');?>"
             autocomplete="username"
      />
      <label for="<?= ConfigConstants::APP_DB_USER ?>"><?= $l->t('Database User');?></label>
      <div id="msgplaceholder"><div class="statusmessage" id="msg"></div></div>
    </fieldset>
    <!-- DATA-BASE password -->
    <fieldset class="<?= $appName ?>_<?= ConfigConstants::APP_DB_PASSWORD ?> flex-container">
      <div class="password-container">
        <input class="<?= $appName ?>-password"
               type="password"
               id="<?= $appName ?>-<?= ConfigConstants::APP_DB_PASSWORD ?>"
               name="<?= ConfigConstants::APP_DB_PASSWORD ?>"
               placeholder="<?= $l->t('New Password');?>" data-typetoggle="#<?= $appName ?>-<?= ConfigConstants::APP_DB_PASSWORD ?>-show"
               autocomplete="current-password"
        />
        <input class="<?= $appName ?>-password-show" type="checkbox" id="<?= $appName ?>-<?= ConfigConstants::APP_DB_PASSWORD ?>-show" name="<?= ConfigConstants::APP_DB_PASSWORD ?>-show" />
        <label class="<?= $appName ?>-password-show" for="<?= $appName ?>-<?= ConfigConstants::APP_DB_PASSWORD ?>-show"><?= $l->t('show');?></label>
      </div>
      <input id="database-password-test-button"
             class="button"
             type="button"
             title="<?= $toolTips['test-dbpassword']; ?>"
             value="<?= $l->t('Test Database Password');?>"
      />
      <div class="statusmessage" id="dbteststatus"></div>
    </fieldset>
    <fieldset id="dbtesting">
    </fieldset>
  </form>
</div>
