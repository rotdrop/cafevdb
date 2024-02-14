<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2020, 2021, 2023, 2024 Claus-Justus Heine
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

$linkToolTip = function(string $tag, ?string $value) use ($toolTips) {
  return empty($value) ? $toolTips[$tag] : $value;
};

$linkTargets = [
  'phpmyadmin' => [
    'name' => $appName . '@phpMyAdmin',
    'placeholder' => $appName . '@phpMyAdmin',
    'label' => $l->t('Link to the database %s', $appName . '@phpMyAdmin'),
  ],
  'phpmyadmincloud' => [
    'name' => 'Cloud@phpMyAdmin',
    'placeholder' => 'Cloud@phpMyAdmin',
    'label' => $l->t('Link to the database %s', 'Cloud@phpMyAdmin'),
  ],
  'sourcecode' => [
    'label' => $l->t('Link to the source-code'),
  ],
  'sourcedocs' => [
    'label' => $l->t('Link to the source-code documentation'),
  ],
  'clouddev' => [
    'label' => $l->t('Link to cloud Developer Information'),
  ],
  'cspfailurereporting' => [
    'label' => $l->t('Link for uploading CSP failure information'),
  ],
];

?>
<div id="tabs-<?php echo $tabNr; ?>" class="personalblock admin devel">
  <form id="develsettings">
    <fieldset id="devlinks"><legend><?php echo $l->t('Links');?></legend>
      <?php
      foreach ($linkTargets as $target => $data) {
        $label = $data['label'] ?? $l->t('Link to "%s"', $data['name']);
        $placeholder = $data['placeholder'] ?? $label;
        $targetValue = $_[$target];
      ?>
      <div class="devlinkgroup">
        <a href="<?php p($targetValue) ?>"
           target="<?php p($appName . ':' . $target); ?>"
           class="button devlinktest"
           id="test<?php p($target); ?>"
           title="<?php echo $toolTips['test-linktarget']; ?>"
        >
          <?php echo $l->t('Test Link'); ?>
        </a>
        <input type="text"
               class="devlink"
               id="<?php p($target); ?>"
               name="<?php p($target); ?>"
               placeholder="<?php p($placeholder); ?>"
               value="<?php p($targetValue); ?>"
               title="<?php p($linkToolTip($target . '-link', $targetValue)); ?>" />
        <label for="<?php p($target); ?>"><?php p($label); ?></label>
      </div>
      <?php } ?>
    </fieldset>
    <span class="statusmessage" id="msg"></span>
  </form>
</div>
