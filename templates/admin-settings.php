<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Common\Util;

script($appName, $assets['js']['asset']);
style($appName, $assets['css']['asset']);

if (empty($wikiVersion)) {
  $wikiHide = '';
  $wikiShow = 'hidden';
} else {
  $wikiHide = 'hidden';
  $wikiShow = '';
}

$userGroupTag = 'orchestraUserGroup';
$wikiNameSpaceTag = 'wikiNameSpace';
$cloudUserBackendConfTag = 'cloudUserBackendConf';

$enableCloudUserBackendConfiguration = $haveCloudUserBackendConfig;
$cloudUserBackendHints = [];
if (!$haveCloudUserBackendConfig) {
  $cloudUserBackendHints[] = $l->t(
    'In order to use the "%1$s"-app to add user-accounts for the orchestra club-members you first have to configure the necessary infra-structure in the personal-settings dialog of this app. Please head over to %2$s. You need to have group-admin rights for the orchestra group "%3$s".', [
      $cloudUserBackend,
      '<a class="external settings" href="' . $personalAppSettingsLink . '" target="' . \md5($personalAppSettingsLink) . '">' . $appName . '</a>',
      $userGroup,
    ]);
}
if (!$cloudUserBackendEnabled) {
  $cloudUserBackendHints[] = $l->t(
    'In order to be able to import the orchestra club-members as cloud-users the
"%1$s"-app needs to be enabled.',
    $cloudUserBackend
  );
  $enableCloudUserBackendConfiguration = false;
}
if (!empty($cloudUserBackendRestrictions)) {
  $cloudUserBackendHints[] = $l->t(
    'The requird user-backend "%1$s" seems to be installed and enabled, however, the following app-restriction have been imposed on the app: "%2$s".', [
      $cloudUserBackend,
      implode(',', $cloudUserBackendRestrictions),
  ]);
  $enableCloudUserBackendConfiguration = false;
}
?>

<div class="section <?php p($appName); ?>-admin-settings">
  <h2 class="heading">Camerata DB</h2>
  <form id="<?php p($appName); ?>-admin-settings">
    <input type="hidden" name="requesttoken" value="<?php p($requesttoken); ?>"/>
    <div>
      <input type="text"
             class="<?php p($userGroupTag); ?>"
             name="<?php p($userGroupTag); ?>"
             id="<?php p($userGroupTag); ?>"
             value="<?php p($userGroup); ?>"
             title="<?php p($toolTips['settings:admin:user-group']); ?>"
             placeholder="<?php p($l->t('Group')); ?>"
             data-cloud-groups='<?php echo json_encode($cloudGroups); ?>'
      />
      <label for="<?php p($userGroupTag); ?>"><?php p($l->t('User Group')); ?></label>
    </div>
    <div class="<?php p($wikiShow); ?>">
      <input type="text"
             class="<?php p($wikiNameSpaceTag); ?>"
             name="<?php p($wikiNameSpaceTag); ?>"
             id="<?php p($wikiNameSpaceTag); ?>"
             value="<?php p($wikiNameSpace); ?>"
             title="<?php p($toolTips['settings:admin:wiki-name-space']); ?>"
             placeholder="<?php p($l->t('Wiki NameSpace'));?>" />
      <label for="<?php p($wikiNameSpaceTag); ?>"><?php p($l->t('Wiki Name-Space')); ?></label>
    </div>
    <div class="<?php p($wikiHide); ?>">
      <?php p($l->t('Wiki is inaccessible')); ?>
    </div>
    <div class="cloud-user-backend">
      <input type="button"
             name="<?php p($cloudUserBackendConfTag); ?>"
             value="<?php p($l->t('Autoconfigure "%s" app', 'user_sql')); ?>"
             id="<?php p($cloudUserBackendConfTag); ?>"
             class="<?php p($cloudUserBackendConfTag); ?>"
             title="<?php p($toolTips['settings:admin:cloud-user-backend-conf']); ?>"
             <?php $enableCloudUserBackendConfiguration || p('disabled'); ?>
      />
      <div class="cloud-user-backend hints">
        <?php foreach ($cloudUserBackendHints as $hint) { ?>
          <div class="hint"><?php echo $hint; ?></div>
        <?php } ?>
      </div>
    </div>
    <span class="msg"></span>
  </form>
</div>
